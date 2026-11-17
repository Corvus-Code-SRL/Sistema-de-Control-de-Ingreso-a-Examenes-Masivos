<?php

namespace Tests\Feature\Exams\RoomDistribution;

use App\Services\Exams\Distribution\DistributionRosterQuery;
use App\Services\Exams\Distribution\RoomDistributionReader;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Mockery\MockInterface;
use Tests\Concerns\SeedsRoomDistribution;
use Tests\TestCase;

class RoomDistributionReaderTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsRoomDistribution;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedExamCatalog();
    }

    public function test_sin_planificacion_retorna_null_incluso_con_ambiente_unico(): void
    {
        foreach ([[3, 3], [6]] as $capacities) {
            $exam = $this->distributionExam(4, $capacities);
            $this->assertNull(app(RoomDistributionReader::class)->assignedRooms((int) $exam->id_examen));
        }
    }

    public function test_conserva_el_ajuste_manual_y_no_escribe_la_planificacion(): void
    {
        $exam = $this->distributionExam(4, [3, 3]);
        $ids = $this->distributionStudentIds($exam);
        $assignments = array_map(fn (int $id, int $index): array => [
            'id_estudiante' => $id, 'id_ambiente' => $index < 2 ? $this->otraAulaId : $this->aulaId,
        ], $ids, array_keys($ids));
        $this->putJson($this->distributionUrl($exam), [
            'version' => $this->distributionVersion($exam), 'asignaciones' => $assignments,
        ])->assertOk();
        $before = app(DistributionRosterQuery::class)->savedRows((int) $exam->id_examen);

        $rooms = app(RoomDistributionReader::class)->assignedRooms((int) $exam->id_examen);

        $this->assertSame(array_column($assignments, 'id_ambiente', 'id_estudiante'),
            array_map(fn (array $room): int => $room['id_ambiente'], $rooms));
        $this->assertSame($before, app(DistributionRosterQuery::class)->savedRows((int) $exam->id_examen));
    }

    public function test_ubica_nuevos_habilitados_solo_en_los_lugares_restantes(): void
    {
        $exam = $this->distributionExam(4, [3, 3]);
        $this->saveDistribution($exam)->assertOk();
        $reader = app(RoomDistributionReader::class);
        $previous = $reader->assignedRooms((int) $exam->id_examen);
        $newId = $this->addDistributionStudent($exam);

        $updated = $reader->assignedRooms((int) $exam->id_examen);

        $this->assertSame($this->aulaId, $updated[$newId]['id_ambiente']);
        foreach ($previous as $id => $room) {
            $this->assertSame($room, $updated[$id]);
        }
        $this->assertCount(4, app(DistributionRosterQuery::class)->savedRows((int) $exam->id_examen));
    }

    public function test_deshabilitados_liberan_lugares_sin_eliminar_sus_filas_guardadas(): void
    {
        $exam = $this->distributionExam(4, [2, 2]);
        $this->saveDistribution($exam)->assertOk();
        [$disabled] = $this->distributionStudentIds($exam);
        $before = app(RoomDistributionReader::class)->assignedRooms((int) $exam->id_examen);
        $this->setDistributionEligibility($exam, $disabled, 'NO_HABILITADO');
        $newId = $this->addDistributionStudent($exam);

        $updated = app(RoomDistributionReader::class)->assignedRooms((int) $exam->id_examen);

        $this->assertArrayNotHasKey($disabled, $updated);
        $this->assertSame($before[$disabled], $updated[$newId]);
        $this->assertDatabaseHas('distribucion_ambiente', [
            'id_examen' => $exam->id_examen, 'id_estudiante' => $disabled,
        ]);
    }

    public function test_nuevos_habilitados_se_reparten_proporcionalmente_sobre_capacidad_restante(): void
    {
        $exam = $this->distributionExam(4, [5, 3]);
        $this->saveDistribution($exam)->assertOk();
        $before = app(RoomDistributionReader::class)->assignedRooms((int) $exam->id_examen);
        $groupId = (int) DB::table('grupo_examen')->where('id_examen', $exam->id_examen)->value('id_grupo');
        $this->enrollStudents($groupId, 3);

        $updated = app(RoomDistributionReader::class)->assignedRooms((int) $exam->id_examen);

        // El plan tiene 3/1; los lugares libres son 2/2, por lo que los tres nuevos ocupan 2/1.
        $this->assertSame([$this->aulaId => 5, $this->otraAulaId => 2],
            array_count_values(array_column($updated, 'id_ambiente')));
        foreach ($before as $id => $room) {
            $this->assertSame($room, $updated[$id]);
        }
    }

    public function test_ambiente_retirado_se_reconcilia_sin_reemplazar_las_asignaciones_validas(): void
    {
        $exam = $this->distributionExam(4, [4, 4]);
        $this->saveDistribution($exam)->assertOk();
        DB::table('examen_ambiente')->where('id_examen', $exam->id_examen)
            ->where('id_ambiente', $this->otraAulaId)->delete();

        $updated = app(RoomDistributionReader::class)->assignedRooms((int) $exam->id_examen);

        $this->assertCount(4, $updated);
        $this->assertSame([$this->aulaId], array_values(array_unique(array_column($updated, 'id_ambiente'))));
    }

    public function test_capacidad_reducida_conserva_los_validos_y_deja_excedentes_sin_ambiente(): void
    {
        $exam = $this->distributionExam(4, [3, 3]);
        $this->saveDistribution($exam)->assertOk();
        $before = app(RoomDistributionReader::class)->assignedRooms((int) $exam->id_examen);
        DB::table('ambiente')->where('id_ambiente', $this->aulaId)->update(['capacidad' => 1]);
        DB::table('ambiente')->where('id_ambiente', $this->otraAulaId)->update(['capacidad' => 2]);

        $updated = app(RoomDistributionReader::class)->assignedRooms((int) $exam->id_examen);

        $this->assertCount(3, $updated);
        $this->assertSame([$this->aulaId => 1, $this->otraAulaId => 2],
            array_count_values(array_column($updated, 'id_ambiente')));
        foreach ($updated as $id => $room) {
            $this->assertSame($before[$id], $room);
        }
    }

    public function test_planificacion_existente_sin_ambientes_no_activa_el_fallback(): void
    {
        $exam = $this->distributionExam();
        $this->saveDistribution($exam)->assertOk();
        DB::table('examen_ambiente')->where('id_examen', $exam->id_examen)->delete();

        $this->assertSame([], app(RoomDistributionReader::class)->assignedRooms((int) $exam->id_examen));
    }

    public function test_excepcional_conserva_su_ambiente_y_reserva_aforo_antes_de_ubicar_nuevos(): void
    {
        $exam = $this->distributionExam(4, [3, 3]);
        $this->saveDistribution($exam)->assertOk();
        [$special] = $this->distributionStudentIds($exam);
        $this->setDistributionEligibility($exam, $special, 'CASO ESPECIAL');
        DB::table('examen_estudiante')->where('id_examen', $exam->id_examen)
            ->where('id_estudiante', $special)->update(['id_ambiente' => $this->otraAulaId]);
        $newId = $this->addDistributionStudent($exam);

        $updated = app(RoomDistributionReader::class)->assignedRooms((int) $exam->id_examen);

        $this->assertSame($this->otraAulaId, $updated[$special]['id_ambiente']);
        $this->assertSame($this->aulaId, $updated[$newId]['id_ambiente']);
        $this->assertSame(3, array_count_values(array_column($updated, 'id_ambiente'))[$this->otraAulaId]);
    }

    public function test_excepcional_omitido_por_la_nomina_consultada_conserva_ambiente_y_reserva(): void
    {
        $exam = $this->distributionExam(4, [3, 3]);
        $this->saveDistribution($exam)->assertOk();
        [$special] = $this->distributionStudentIds($exam);
        $this->setDistributionEligibility($exam, $special, 'CASO ESPECIAL');
        DB::table('examen_estudiante')->where('id_examen', $exam->id_examen)
            ->where('id_estudiante', $special)->update(['id_ambiente' => $this->otraAulaId]);
        $newId = $this->addDistributionStudent($exam);
        $students = array_values(array_filter(
            app(DistributionRosterQuery::class)->eligibleStudents((int) $exam->id_examen),
            fn (array $student): bool => $student['id_estudiante'] !== $special
        ));
        // Las FK exigen grupo; se simula solo la omisión de expected(), sin desactivar integridad referencial.
        $this->partialMock(DistributionRosterQuery::class, function (MockInterface $mock) use ($students): void {
            $mock->shouldReceive('eligibleStudents')->once()->andReturn($students);
        });

        $updated = app(RoomDistributionReader::class)->assignedRooms((int) $exam->id_examen);

        $this->assertSame(['id_ambiente' => $this->otraAulaId, 'nro_aula' => '692B'], $updated[$special]);
        $this->assertCount(5, $updated);
        $this->assertSame($this->aulaId, $updated[$newId]['id_ambiente']);
        $this->assertSame(3, array_count_values(array_column($updated, 'id_ambiente'))[$this->otraAulaId]);
    }

    public function test_no_agrava_un_exceso_preexistente_de_excepcionales_ni_cambia_sus_ambientes(): void
    {
        $exam = $this->distributionExam(4, [3, 3]);
        $this->saveDistribution($exam)->assertOk();
        $specialIds = array_slice($this->distributionStudentIds($exam), 0, 2);
        foreach ($specialIds as $special) {
            $this->setDistributionEligibility($exam, $special, 'CASO ESPECIAL');
            DB::table('examen_estudiante')->where('id_examen', $exam->id_examen)
                ->where('id_estudiante', $special)->update(['id_ambiente' => $this->otraAulaId]);
        }
        DB::table('ambiente')->where('id_ambiente', $this->otraAulaId)->update(['capacidad' => 1]);
        $newId = $this->addDistributionStudent($exam);

        $updated = app(RoomDistributionReader::class)->assignedRooms((int) $exam->id_examen);

        foreach ($specialIds as $special) {
            $this->assertSame($this->otraAulaId, $updated[$special]['id_ambiente']);
        }
        $this->assertSame($this->aulaId, $updated[$newId]['id_ambiente']);
        $this->assertSame(2, array_count_values(array_column($updated, 'id_ambiente'))[$this->otraAulaId]);
    }

    public function test_excepcional_sin_ambiente_usa_los_lugares_del_plan(): void
    {
        $exam = $this->distributionExam(4, [3, 3]);
        $this->saveDistribution($exam)->assertOk();
        [$special] = $this->distributionStudentIds($exam);
        $this->setDistributionEligibility($exam, $special, 'CASO ESPECIAL');

        $this->assertSame($this->aulaId,
            app(RoomDistributionReader::class)->assignedRooms((int) $exam->id_examen)[$special]['id_ambiente']);
    }

    public function test_confirmacion_vacia_mantiene_la_limitacion_del_marcador_ausente(): void
    {
        $exam = $this->distributionExam(0);
        $this->saveDistribution($exam)->assertOk();

        $this->assertNull(app(RoomDistributionReader::class)->assignedRooms((int) $exam->id_examen));
    }
}
