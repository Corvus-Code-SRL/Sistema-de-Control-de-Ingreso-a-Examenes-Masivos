<?php

namespace Tests\Feature\Exams\RoomDistribution;

use App\Services\Exams\Distribution\DistributionRosterQuery;
use App\Services\Exams\Distribution\RoomDistributionReader;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
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

    public function test_sin_planificacion_no_hay_ambientes_incluso_con_ambiente_unico(): void
    {
        foreach ([[3, 3], [6]] as $capacities) {
            $exam = $this->distributionExam(4, $capacities);
            $this->assertSame([], app(RoomDistributionReader::class)->assignedRooms((int) $exam->id_examen));
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

    public function test_un_nuevo_habilitado_queda_sin_ambiente(): void
    {
        $exam = $this->distributionExam(4, [3, 3]);
        $this->saveDistribution($exam)->assertOk();
        $reader = app(RoomDistributionReader::class);
        $previous = $reader->assignedRooms((int) $exam->id_examen);
        $newId = $this->addDistributionStudent($exam);

        $updated = $reader->assignedRooms((int) $exam->id_examen);

        $this->assertArrayNotHasKey($newId, $updated);
        $this->assertSame($previous, $updated);
    }

    public function test_deshabilitados_liberan_lugares_sin_eliminar_sus_filas_guardadas(): void
    {
        $exam = $this->distributionExam(4, [2, 2]);
        $this->saveDistribution($exam)->assertOk();
        [$disabled] = $this->distributionStudentIds($exam);
        $this->setDistributionEligibility($exam, $disabled, 'NO_HABILITADO');

        $updated = app(RoomDistributionReader::class)->assignedRooms((int) $exam->id_examen);

        $this->assertArrayNotHasKey($disabled, $updated);
        $this->assertCount(3, $updated);
        $this->assertDatabaseHas('distribucion_ambiente', [
            'id_examen' => $exam->id_examen, 'id_estudiante' => $disabled,
        ]);
    }

    public function test_ambiente_retirado_se_reconcilia_sin_reemplazar_las_asignaciones_validas(): void
    {
        $exam = $this->distributionExam(4, [4, 4]);
        $this->saveDistribution($exam)->assertOk();
        DB::table('examen_ambiente')->where('id_examen', $exam->id_examen)
            ->where('id_ambiente', $this->otraAulaId)->delete();

        $updated = app(RoomDistributionReader::class)->assignedRooms((int) $exam->id_examen);

        $this->assertCount(2, $updated);
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

    public function test_planificacion_sin_ambientes_vigentes_queda_vacia(): void
    {
        $exam = $this->distributionExam();
        $this->saveDistribution($exam)->assertOk();
        DB::table('examen_ambiente')->where('id_examen', $exam->id_examen)->delete();

        $this->assertSame([], app(RoomDistributionReader::class)->assignedRooms((int) $exam->id_examen));
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

        $this->assertSame([], app(RoomDistributionReader::class)->assignedRooms((int) $exam->id_examen));
    }
}
