<?php

namespace Tests\Feature\EntryControl;

use App\Models\Exam;
use App\Models\User;
use App\Services\EntryControl\RoomAssignmentService;
use App\Services\EntryControl\EntryControlSnapshotService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsExamCatalog;
use Tests\TestCase;

/**
 * HU-023: el estudiante excepcional pasa por el control de ingreso como cualquier estudiante del examen.
 */
class ExceptionalStudentEntryTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsExamCatalog;

    private const SIS = '202600888';

    private const REASON = 'Inscripción tardía aprobada';

    private Exam $exam;

    private int $studentId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedExamCatalog();

        $this->exam = $this->createExam(['estado' => Exam::EN_CURSO], [$this->aulaId]);
        DB::table('grupo_examen')->insert([
            'id_examen' => $this->exam->id_examen,
            'id_grupo' => $this->grupoPropioId,
        ]);

        $group = DB::table('grupo')->where('id_grupo', $this->grupoPropioId)->first();
        DB::table('grupo_oferta')->insertOrIgnore([
            'id_grupo' => $this->grupoPropioId,
            'id_carrera' => $group->id_carrera,
            'id_materia' => $group->id_materia,
            'id_gestion_periodo' => $group->id_gestion_periodo,
        ]);

        $this->studentId = (int) DB::table('estudiante')->insertGetId([
            'cod_sis' => self::SIS,
            'ci' => '7654321',
            'nombre' => 'Luis',
            'apellido_paterno' => 'Rojas',
            'apellido_materno' => 'Vega',
            'estado' => 'ACTIVO',
        ], 'id_estudiante');

        DB::table('examen_estudiante')->insert([
            'id_examen' => $this->exam->id_examen,
            'id_estudiante' => $this->studentId,
            'id_grupo' => null,
            'estado_habilitacion' => 'HABILITADO',
            'estado_ingreso' => 'NO_INGRESO',
            'id_ambiente' => $this->aulaId,
            'es_excepcional' => true,
            'motivo_excepcional' => self::REASON,
            'id_usuario_docente_excepcional' => $this->docenteId,
            'id_grupo_excepcional' => $this->grupoPropioId,
            'id_carrera_excepcional' => $group->id_carrera,
        ]);

        app(RoomAssignmentService::class)->prepare($this->exam->id_examen);
        $this->actingAs(User::query()->findOrFail($this->docenteId));
    }

    public function test_la_verificacion_lo_encuentra_por_sis_y_por_ci_y_muestra_la_marca(): void
    {
        foreach ([['cod_sis' => self::SIS], ['ci' => '7654321']] as $input) {
            $this->postJson(
                "/api/control-ingreso/examenes/{$this->exam->id_examen}/verificar",
                $input + ['id_ambiente' => $this->aulaId]
            )
                ->assertOk()
                ->assertJsonPath('data.veredicto', 'AUTORIZADO')
                ->assertJsonPath('data.estudiante.id_estudiante', $this->studentId)
                ->assertJsonPath('data.es_excepcional', true)
                ->assertJsonPath('data.motivo_excepcional', self::REASON)
                ->assertJsonPath('data.grupo.id_grupo', $this->grupoPropioId);
        }
    }

    public function test_confirmar_su_ingreso_lo_marca_ingresado_y_conserva_su_condicion_de_excepcional(): void
    {
        $this->postJson("/api/control-ingreso/examenes/{$this->exam->id_examen}/confirmar-ingreso", [
            'id_estudiante' => $this->studentId,
            'id_ambiente' => $this->aulaId,
        ])->assertCreated();

        $this->assertDatabaseHas('examen_estudiante', [
            'id_examen' => $this->exam->id_examen,
            'id_estudiante' => $this->studentId,
            'estado_ingreso' => 'INGRESO',
            'es_excepcional' => true,
            'id_grupo' => null,
            'id_grupo_efectivo' => $this->grupoPropioId,
        ]);

        // Un segundo intento es un duplicado, igual que para cualquier estudiante.
        $this->postJson("/api/control-ingreso/examenes/{$this->exam->id_examen}/confirmar-ingreso", [
            'id_estudiante' => $this->studentId,
            'id_ambiente' => $this->aulaId,
        ])->assertStatus(409);
    }

    public function test_el_registro_de_verificacion_lo_arma_igual_desde_postgresql(): void
    {
        $record = app(EntryControlSnapshotService::class)->recordFor($this->exam, $this->studentId);

        $this->assertNotNull($record);
        $this->assertTrue($record['es_excepcional']);
        $this->assertSame(self::REASON, $record['motivo_excepcional']);
        $this->assertSame($this->aulaId, $record['ambiente_asignado']['id_ambiente']);
        $this->assertArrayNotHasKey('motivo_excepcional', $record['grupo']);
    }
}
