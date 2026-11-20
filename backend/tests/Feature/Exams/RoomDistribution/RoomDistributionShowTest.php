<?php

namespace Tests\Feature\Exams\RoomDistribution;

use App\Models\Exam;
use App\Models\Group;
use App\Support\Exams\DistributionMessages;
use App\Support\RecordStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsRoomDistribution;
use Tests\TestCase;

class RoomDistributionShowTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsRoomDistribution;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedExamCatalog();
    }

    public function test_un_solo_ambiente_asigna_todos_sin_persistir(): void
    {
        $exam = $this->distributionExam(4, [30]);
        $this->getJson($this->distributionUrl($exam))->assertOk()
            ->assertJsonPath('data.editable', false)
            ->assertJsonPath('data.ambientes.0.asignados', 4)
            ->assertJsonPath('data.ambientes.0.capacidad', 30)
            ->assertJsonPath('data.total_habilitados', 4)
            ->assertJsonPath('data.total_no_habilitados', 0)
            ->assertJsonPath('data.estudiantes.0.id_ambiente', $this->aulaId);

        $this->assertSame(0, DB::table('distribucion_ambiente')->count());
    }

    public function test_excluye_no_habilitados_e_incluye_casos_especiales_y_estudiantes_sin_fila(): void
    {
        $exam = $this->distributionExam(3);
        [$disabled, $special] = $this->distributionStudentIds($exam);
        $this->setDistributionEligibility($exam, $disabled, 'NO_HABILITADO');
        $this->setDistributionEligibility($exam, $special, 'CASO ESPECIAL');

        $response = $this->getJson($this->distributionUrl($exam))->assertOk()
            ->assertJsonPath('data.total_habilitados', 2)->assertJsonPath('data.total_no_habilitados', 1)
            ->assertJsonCount(2, 'data.estudiantes');

        $this->assertNotContains($disabled, array_column($response->json('data.estudiantes'), 'id_estudiante'));
        $this->assertContains($special, array_column($response->json('data.estudiantes'), 'id_estudiante'));
        $this->assertSame([null, null], array_column($response->json('data.estudiantes'), 'id_ambiente'));
    }

    public function test_cuenta_cada_estado_una_vez_aunque_el_estudiante_pertenezca_a_varios_grupos(): void
    {
        $exam = $this->distributionExam(4);
        $ids = $this->distributionStudentIds($exam);
        $this->setDistributionEligibility($exam, $ids[0], 'HABILITADO');
        $this->setDistributionEligibility($exam, $ids[1], 'NO_HABILITADO');
        $this->setDistributionEligibility($exam, $ids[2], 'CASO ESPECIAL');
        DB::table('grupo_examen')->insert(['id_examen' => $exam->id_examen, 'id_grupo' => $this->grupoAjenoId]);

        foreach ($ids as $id) {
            DB::table('grupo_estudiante')->insert([
                'id_grupo' => $this->grupoAjenoId, 'id_estudiante' => $id,
                'fecha_inscripcion' => '2026-11-17', 'estado' => RecordStatus::ACTIVE,
            ]);
        }

        $response = $this->getJson($this->distributionUrl($exam))->assertOk()
            ->assertJsonPath('data.total_habilitados', 3)->assertJsonPath('data.total_no_habilitados', 1)
            ->assertJsonCount(3, 'data.estudiantes');
        $this->assertArrayNotHasKey('mensaje', $response->json());
        $this->assertSame(3, count(array_unique(array_column($response->json('data.estudiantes'), 'id_estudiante'))));
        $this->assertDatabaseMissing('examen_estudiante', [
            'id_examen' => $exam->id_examen, 'id_estudiante' => $ids[3],
        ]);
        $this->saveDistribution($exam)->assertOk()
            ->assertJsonPath('data.total_habilitados', 3)->assertJsonPath('data.total_no_habilitados', 1);
    }

    public function test_el_conteo_en_finalizados_reutiliza_la_nomina_congelada(): void
    {
        $exam = $this->distributionExam(4);
        $ids = $this->distributionStudentIds($exam);
        $this->setDistributionEligibility($exam, $ids[0], 'HABILITADO');
        $this->setDistributionEligibility($exam, $ids[1], 'NO_HABILITADO');
        $this->setDistributionEligibility($exam, $ids[2], 'CASO ESPECIAL');
        $this->putExamInState((int) $exam->id_examen, Exam::FINALIZADO);

        $this->getJson($this->distributionUrl($exam))->assertOk()
            ->assertJsonPath('data.total_habilitados', 2)->assertJsonPath('data.total_no_habilitados', 1)
            ->assertJsonCount(2, 'data.estudiantes');
    }

    public function test_ambiente_unico_insuficiente_informa_faltantes_y_respeta_el_aforo(): void
    {
        $exam = $this->distributionExam(4, [2]);
        $response = $this->getJson($this->distributionUrl($exam))->assertOk()
            ->assertJsonPath('data.editable', false)->assertJsonPath('data.examen.editable', false)
            ->assertJsonPath('data.motivo_solo_lectura', sprintf(
                DistributionMessages::SINGLE_ROOM_INSUFFICIENT, '691A', 2, 4, 2
            ))
            ->assertJsonPath('data.ambientes.0.asignados', 2)->assertJsonPath('data.ambientes.0.capacidad', 2)
            ->assertJsonPath('data.faltan_asientos', 2);
        $this->assertSame(2, count(array_filter($response->json('data.estudiantes'), fn (array $student): bool =>
            $student['id_ambiente'] === null)));
        $this->assertSame(0, DB::table('distribucion_ambiente')->count());
    }

    public function test_informa_la_cantidad_exacta_de_lugares_faltantes(): void
    {
        $exam = $this->distributionExam(7, [2, 3]);

        $this->getJson($this->distributionUrl($exam))->assertOk()
            ->assertJsonPath('data.capacidad_total', 5)->assertJsonPath('data.faltan_asientos', 2);
    }

    public function test_concilia_nuevos_habilitados_y_desvincula_deshabilitados_sin_escribir(): void
    {
        $exam = $this->distributionExam();
        $saved = $this->saveDistribution($exam)->assertOk()->json('data');
        $disabled = $saved['estudiantes'][0]['id_estudiante'];
        $this->setDistributionEligibility($exam, $disabled, 'NO_HABILITADO');
        $newId = $this->addDistributionStudent($exam);

        $response = $this->getJson($this->distributionUrl($exam))->assertOk();
        $students = array_column($response->json('data.estudiantes'), null, 'id_estudiante');
        $this->assertArrayNotHasKey($disabled, $students);
        $this->assertNull($students[$newId]['id_ambiente']);
        $this->assertSame(3, array_sum(array_column($response->json('data.ambientes'), 'asignados')));
        $this->assertSame($saved['version'], $response->json('data.version'));
        $this->assertSame(4, DB::table('distribucion_ambiente')->where('id_examen', $exam->id_examen)->count());
    }

    public function test_ambiente_quitado_o_capacidad_reducida_dejan_excedentes_sin_ambiente(): void
    {
        $exam = $this->distributionExam(4, [2, 2]);
        $this->saveDistribution($exam)->assertOk();
        DB::table('examen_ambiente')->where('id_examen', $exam->id_examen)
            ->where('id_ambiente', $this->otraAulaId)->delete();
        DB::table('examen_ambiente')->insert(['id_examen' => $exam->id_examen, 'id_ambiente' => $this->aulaInactivaId]);
        DB::table('ambiente')->where('id_ambiente', $this->aulaId)->update(['capacidad' => 1]);

        $response = $this->getJson($this->distributionUrl($exam))->assertOk();

        $this->assertSame(1, array_sum(array_column($response->json('data.ambientes'), 'asignados')));
        $this->assertCount(3, array_filter($response->json('data.estudiantes'), fn (array $s): bool =>
            $s['id_ambiente'] === null));
    }

    public function test_nomina_de_varios_grupos_se_entrega_sin_duplicados(): void
    {
        $exam = $this->distributionExam(2);
        $ids = $this->distributionStudentIds($exam);
        $group = Group::create([
            'id_carrera' => $this->sistemasId, 'id_materia' => $this->calculoId, 'num_grupo' => '500',
            'id_usuario_docente' => $this->otroDocenteId, 'id_gestion_periodo' => $this->periodoActivoId,
            'estado' => RecordStatus::ACTIVE,
        ]);
        DB::table('grupo_examen')->insert(['id_examen' => $exam->id_examen, 'id_grupo' => $group->id_grupo]);
        DB::table('grupo_estudiante')->insert([
            'id_grupo' => $group->id_grupo, 'id_estudiante' => $ids[0],
            'fecha_inscripcion' => '2026-11-17', 'estado' => RecordStatus::ACTIVE,
        ]);

        $this->getJson($this->distributionUrl($exam))->assertOk()
            ->assertJsonPath('data.total_habilitados', 2)->assertJsonCount(2, 'data.estudiantes');
        $this->saveDistribution($exam)->assertOk();
        $this->assertSame(2, DB::table('distribucion_ambiente')->where('id_examen', $exam->id_examen)->count());
    }

    public function test_sin_ambientes_o_habilitados_no_falla_la_consulta(): void
    {
        $exam = $this->distributionExam(0, []);

        $this->getJson($this->distributionUrl($exam))->assertOk()
            ->assertJsonPath('data.editable', false)->assertJsonPath('data.total_habilitados', 0)
            ->assertJsonPath('data.capacidad_total', 0)->assertJsonPath('data.faltan_asientos', 0)
            ->assertJsonCount(0, 'data.estudiantes')->assertJsonCount(0, 'data.ambientes');
    }
}
