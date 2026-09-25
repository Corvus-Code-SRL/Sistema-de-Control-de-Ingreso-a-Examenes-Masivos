<?php

namespace Tests\Feature\Exams;

use App\Models\Exam;
use App\Models\Group;
use App\Services\Exams\ExamParticipantService;
use App\Support\RecordStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsExamCatalog;
use Tests\TestCase;

/**
 * Asignación de grupos a un examen.
 *
 * Asignar un grupo solo escribe el vínculo grupo-examen: examen_estudiante registra
 * ingresos reales, así que nunca se llena aquí y los participantes se derivan de la
 * nómina (ExamParticipantService).
 */
class AssignGroupsTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsExamCatalog;

    private int $groupWithoutRosterId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedExamCatalog();

        $this->groupWithoutRosterId = (int) Group::query()
            ->where('id_carrera', $this->sistemasId)
            ->where('id_materia', $this->calculoId)
            ->where('num_grupo', '2')
            ->value('id_grupo');
    }

    public function test_crear_examen_asigna_grupos_sin_copiar_la_nomina(): void
    {
        $response = $this->postJson('/api/examenes', $this->validPayload())
            ->assertCreated()
            ->assertJsonPath('data.grupos.0.id_grupo', $this->grupoPropioId)
            ->assertJsonPath('data.grupos.0.cantidad_estudiantes', 2)
            ->assertJsonPath('data.grupos.0.tiene_nomina', true);

        $examId = (int) $response->json('data.id_examen');

        $this->assertDatabaseHas('grupo_examen', [
            'id_examen' => $examId,
            'id_grupo' => $this->grupoPropioId,
        ]);

        $this->assertSame(
            0,
            DB::table('examen_estudiante')->where('id_examen', $examId)->count()
        );
        $this->assertSame(
            ['esperados' => 2, 'ingresados' => 0, 'pendientes' => 2],
            app(ExamParticipantService::class)->counts($examId)
        );
    }

    public function test_la_nomina_puede_cambiar_mientras_el_examen_esta_programado(): void
    {
        $exam = $this->createExam();

        $this->postJson("/api/examenes/{$exam->id_examen}/grupos", [
            'grupos' => [$this->grupoPropioId],
        ])->assertOk();

        $this->enrollStudents($this->grupoPropioId, 3);

        $this->assertSame(
            ['esperados' => 5, 'ingresados' => 0, 'pendientes' => 5],
            app(ExamParticipantService::class)->counts($exam->id_examen)
        );
    }

    public function test_crear_examen_revierte_todo_si_el_grupo_no_tiene_nomina(): void
    {
        $this->postJson('/api/examenes', $this->validPayload([
            'grupos' => [$this->groupWithoutRosterId],
        ]))->assertUnprocessable()->assertJsonValidationErrors('grupos');

        $this->assertSame(0, Exam::query()->count());
        $this->assertSame(0, DB::table('examen_ambiente')->count());
        $this->assertSame(0, DB::table('grupo_examen')->count());
        $this->assertSame(0, DB::table('examen_estudiante')->count());
    }

    public function test_endpoint_reemplaza_grupos_en_una_transaccion(): void
    {
        $exam = $this->createExam();
        $this->enrollStudents($this->groupWithoutRosterId, 1);

        $this->postJson("/api/examenes/{$exam->id_examen}/grupos", [
            'grupos' => [$this->grupoPropioId],
        ])->assertOk();

        $this->postJson("/api/examenes/{$exam->id_examen}/grupos", [
            'grupos' => [$this->groupWithoutRosterId],
        ])
            ->assertOk()
            ->assertJsonPath('mensaje', 'Grupos asignados al examen correctamente.');

        $this->assertDatabaseMissing('grupo_examen', [
            'id_examen' => $exam->id_examen,
            'id_grupo' => $this->grupoPropioId,
        ]);
        $this->assertDatabaseHas('grupo_examen', [
            'id_examen' => $exam->id_examen,
            'id_grupo' => $this->groupWithoutRosterId,
        ]);
        $this->assertSame(
            0,
            DB::table('examen_estudiante')->where('id_examen', $exam->id_examen)->count()
        );
        $this->assertSame(
            1,
            app(ExamParticipantService::class)->counts($exam->id_examen)['esperados']
        );
    }

    public function test_rechaza_grupo_de_otro_docente(): void
    {
        $this->enrollStudents($this->grupoAjenoId, 1);

        $this->assertGroupIsRejected($this->grupoAjenoId);
    }

    public function test_rechaza_grupo_de_otro_par_materia_carrera(): void
    {
        $group = Group::create([
            'id_carrera' => $this->sistemasId,
            'id_materia' => $this->basesDatosId,
            'num_grupo' => '9',
            'gestion' => '2026',
            'estado' => RecordStatus::ACTIVE,
            'id_usuario_docente' => $this->docenteId,
            'id_periodo' => $this->periodoActivoId,
        ]);
        $this->enrollStudents($group->id_grupo, 1);

        $this->assertGroupIsRejected($group->id_grupo);
    }

    public function test_rechaza_grupo_sin_nomina(): void
    {
        $this->assertGroupIsRejected($this->groupWithoutRosterId);
    }

    public function test_rechaza_grupo_de_la_misma_materia_en_otra_carrera(): void
    {
        // Calculo II también se dicta en Informatica: mismo id_materia, otro id_carrera.
        $group = Group::create([
            'id_carrera' => $this->informaticaId,
            'id_materia' => $this->calculoId,
            'num_grupo' => '9',
            'gestion' => '2026',
            'estado' => RecordStatus::ACTIVE,
            'id_usuario_docente' => $this->docenteId,
            'id_periodo' => $this->periodoActivoId,
        ]);
        $this->enrollStudents($group->id_grupo, 1);

        $this->assertGroupIsRejected($group->id_grupo);
    }

    public function test_grupos_de_dos_docentes_en_el_mismo_examen_no_se_fusionan(): void
    {
        // Simula un examen con grupos ya vinculados por otro docente (escenario que hoy
        // no se puede alcanzar por la API, pero que el servicio no debe corromper si el
        // módulo assistants llega a habilitarlo).
        $exam = $this->createExam();
        $this->enrollStudents($this->grupoAjenoId, 1);

        DB::table('grupo_examen')->insert([
            'id_examen' => $exam->id_examen,
            'id_grupo' => $this->grupoAjenoId,
        ]);
        // Un ingreso real ya registrado de ese grupo: asignar grupos no lo toca.
        DB::table('examen_estudiante')->insert([
            'id_examen' => $exam->id_examen,
            'id_estudiante' => DB::table('grupo_estudiante')
                ->where('id_grupo', $this->grupoAjenoId)
                ->value('id_estudiante'),
            'id_grupo' => $this->grupoAjenoId,
            'estado_habilitacion' => 'HABILITADO',
            'estado_ingreso' => 'NO_INGRESO',
        ]);

        $this->postJson("/api/examenes/{$exam->id_examen}/grupos", [
            'grupos' => [$this->grupoPropioId],
        ])->assertOk();

        $this->assertDatabaseHas('grupo_examen', [
            'id_examen' => $exam->id_examen,
            'id_grupo' => $this->grupoAjenoId,
        ]);
        $this->assertDatabaseHas('grupo_examen', [
            'id_examen' => $exam->id_examen,
            'id_grupo' => $this->grupoPropioId,
        ]);
        $this->assertDatabaseHas('examen_estudiante', [
            'id_examen' => $exam->id_examen,
            'id_grupo' => $this->grupoAjenoId,
        ]);
    }

    public function test_agrega_y_quita_grupos_propios_mientras_esta_programado(): void
    {
        $segundoGrupoPropioId = (int) Group::query()
            ->where('id_carrera', $this->sistemasId)
            ->where('id_materia', $this->calculoId)
            ->where('num_grupo', '2')
            ->value('id_grupo');
        $this->enrollStudents($segundoGrupoPropioId, 1);

        $exam = $this->createExam();

        $this->postJson("/api/examenes/{$exam->id_examen}/grupos", [
            'grupos' => [$this->grupoPropioId, $segundoGrupoPropioId],
        ])->assertOk();

        $this->assertDatabaseHas('grupo_examen', ['id_examen' => $exam->id_examen, 'id_grupo' => $this->grupoPropioId]);
        $this->assertDatabaseHas('grupo_examen', ['id_examen' => $exam->id_examen, 'id_grupo' => $segundoGrupoPropioId]);

        $this->postJson("/api/examenes/{$exam->id_examen}/grupos", [
            'grupos' => [$this->grupoPropioId],
        ])->assertOk();

        $this->assertDatabaseHas('grupo_examen', ['id_examen' => $exam->id_examen, 'id_grupo' => $this->grupoPropioId]);
        $this->assertDatabaseMissing('grupo_examen', ['id_examen' => $exam->id_examen, 'id_grupo' => $segundoGrupoPropioId]);
    }

    public function test_rechaza_un_estudiante_repetido_en_dos_grupos_seleccionados(): void
    {
        $otherGroup = Group::create([
            'id_carrera' => $this->sistemasId,
            'id_materia' => $this->calculoId,
            'num_grupo' => '8',
            'gestion' => '2026',
            'estado' => RecordStatus::ACTIVE,
            'id_usuario_docente' => $this->docenteId,
            'id_periodo' => $this->periodoActivoId,
        ]);
        $studentId = DB::table('grupo_estudiante')
            ->where('id_grupo', $this->grupoPropioId)
            ->value('id_estudiante');

        DB::table('grupo_estudiante')->insert([
            'id_grupo' => $otherGroup->id_grupo,
            'id_estudiante' => $studentId,
            'fecha_inscripcion' => '2026-02-15',
            'estado' => RecordStatus::ACTIVE,
        ]);

        $exam = $this->createExam();
        $this->postJson("/api/examenes/{$exam->id_examen}/grupos", [
            'grupos' => [$this->grupoPropioId, $otherGroup->id_grupo],
        ])->assertUnprocessable()->assertJsonValidationErrors('grupos');

        $this->assertSame(0, DB::table('grupo_examen')->count());
        $this->assertSame(0, DB::table('examen_estudiante')->count());
    }

    public function test_un_reemplazo_invalido_conserva_los_grupos_anteriores(): void
    {
        $exam = $this->createExam();

        $this->postJson("/api/examenes/{$exam->id_examen}/grupos", [
            'grupos' => [$this->grupoPropioId],
        ])->assertOk();

        $this->postJson("/api/examenes/{$exam->id_examen}/grupos", [
            'grupos' => [$this->groupWithoutRosterId],
        ])->assertUnprocessable()->assertJsonValidationErrors('grupos');

        $this->assertDatabaseHas('grupo_examen', [
            'id_examen' => $exam->id_examen,
            'id_grupo' => $this->grupoPropioId,
        ]);
        $this->assertDatabaseMissing('grupo_examen', [
            'id_examen' => $exam->id_examen,
            'id_grupo' => $this->groupWithoutRosterId,
        ]);
        $this->assertSame(0, DB::table('examen_estudiante')->count());
    }

    public function test_rechaza_modificar_examen_de_otro_docente(): void
    {
        $exam = $this->createExam(['id_usuario_docente' => $this->otroDocenteId]);

        $this->postJson("/api/examenes/{$exam->id_examen}/grupos", [
            'grupos' => [$this->grupoPropioId],
        ])->assertForbidden();

        $this->assertSame(0, DB::table('grupo_examen')->count());
        $this->assertSame(0, DB::table('examen_estudiante')->count());
    }

    public function test_rechaza_modificar_grupos_si_el_examen_ya_no_esta_programado(): void
    {
        $exam = $this->createExam(['estado' => Exam::EN_INGRESO]);

        $this->postJson("/api/examenes/{$exam->id_examen}/grupos", [
            'grupos' => [$this->grupoPropioId],
        ])->assertStatus(409);

        $this->assertSame(0, DB::table('grupo_examen')->count());
        $this->assertSame(0, DB::table('examen_estudiante')->count());
    }

    public function test_rechaza_grupo_inexistente_sin_modificar_el_examen(): void
    {
        $exam = $this->createExam();

        $this->postJson("/api/examenes/{$exam->id_examen}/grupos", [
            'grupos' => [999999],
        ])->assertUnprocessable()->assertJsonValidationErrors('grupos.0');

        $this->assertSame(0, DB::table('grupo_examen')->count());
        $this->assertSame(0, DB::table('examen_estudiante')->count());
    }

    private function assertGroupIsRejected(int $groupId): void
    {
        $exam = $this->createExam();

        $this->postJson("/api/examenes/{$exam->id_examen}/grupos", [
            'grupos' => [$groupId],
        ])->assertUnprocessable()->assertJsonValidationErrors('grupos');

        $this->assertSame(0, DB::table('grupo_examen')->count());
        $this->assertSame(0, DB::table('examen_estudiante')->count());
    }
}
