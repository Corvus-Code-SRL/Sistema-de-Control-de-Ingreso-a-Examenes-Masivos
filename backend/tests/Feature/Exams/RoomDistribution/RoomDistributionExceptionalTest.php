<?php

namespace Tests\Feature\Exams\RoomDistribution;

use App\Models\Exam;
use App\Services\EntryControl\RoomAssignmentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsRoomDistribution;
use Tests\TestCase;

/** HU-031 con HU-023: el excepcional se registra con el control abierto y nunca forma parte de la distribución. */
class RoomDistributionExceptionalTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsRoomDistribution;

    private Exam $exam;

    private int $exceptionalId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedExamCatalog();
        $this->exam = $this->distributionExam(4, [2, 2]);
        $this->saveDistribution($this->exam)->assertOk();
        app(RoomAssignmentService::class)->prepare((int) $this->exam->id_examen);
        $this->registerExceptional();
        app(RoomAssignmentService::class)->prepare((int) $this->exam->id_examen);
    }

    public function test_la_vista_no_lo_lista_ni_lo_cuenta_ni_lo_muestra_sin_ambiente(): void
    {
        $data = $this->getJson($this->distributionUrl($this->exam))->assertOk()->json('data');

        $this->assertSame(4, $data['total_habilitados']);
        $this->assertSame(0, $data['faltan_asientos']);
        $this->assertSame([2, 2], array_column($data['ambientes'], 'asignados'));
        $this->assertNotContains($this->exceptionalId, array_column($data['estudiantes'], 'id_estudiante'));
        $this->assertNotContains(null, array_column($data['estudiantes'], 'id_ambiente'));
    }

    public function test_su_ambiente_elegido_es_el_asignado_en_el_control_de_ingreso(): void
    {
        $sis = (string) DB::table('estudiante')->where('id_estudiante', $this->exceptionalId)->value('cod_sis');

        $this->postJson("/api/control-ingreso/examenes/{$this->exam->id_examen}/verificar", [
            'cod_sis' => $sis, 'id_ambiente' => $this->aulaId,
        ])->assertOk()
            ->assertJsonPath('data.ambiente_asignado.id_ambiente', $this->otraAulaId)
            ->assertJsonPath('data.avisos_ambiente.0.tipo', 'FUERA_DE_AMBIENTE');

        $this->postJson("/api/control-ingreso/examenes/{$this->exam->id_examen}/verificar", [
            'cod_sis' => $sis, 'id_ambiente' => $this->otraAulaId,
        ])->assertOk()->assertJsonPath('data.avisos_ambiente', []);
    }

    private function registerExceptional(): void
    {
        $groupId = (int) DB::table('grupo_examen')->where('id_examen', $this->exam->id_examen)->value('id_grupo');
        $group = DB::table('grupo')->where('id_grupo', $groupId)->first();
        DB::table('grupo_oferta')->insertOrIgnore([
            'id_grupo' => $groupId,
            'id_carrera' => $group->id_carrera,
            'id_materia' => $group->id_materia,
            'id_gestion_periodo' => $group->id_gestion_periodo,
        ]);
        $this->exceptionalId = (int) DB::table('estudiante')->insertGetId([
            'cod_sis' => '202600777', 'ci' => '7654399', 'nombre' => 'Eva',
            'apellido_paterno' => 'Abad', 'estado' => 'ACTIVO',
        ], 'id_estudiante');
        DB::table('examen_estudiante')->insert([
            'id_examen' => $this->exam->id_examen,
            'id_estudiante' => $this->exceptionalId,
            'id_grupo' => null,
            'estado_habilitacion' => 'HABILITADO',
            'estado_ingreso' => 'NO_INGRESO',
            'id_ambiente' => $this->otraAulaId,
            'es_excepcional' => true,
            'motivo_excepcional' => 'Inscripción tardía aprobada',
            'id_usuario_docente_excepcional' => $this->docenteId,
            'id_grupo_excepcional' => $groupId,
            'id_carrera_excepcional' => $group->id_carrera,
        ]);
    }
}
