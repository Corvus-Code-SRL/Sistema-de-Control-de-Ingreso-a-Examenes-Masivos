<?php

namespace Tests\Feature\Exams;

use App\Models\Exam;
use App\Models\Group;
use App\Support\RecordStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsExamCatalog;
use Tests\TestCase;

/**
 * Asignación de grupos y generación de la nómina habilitada del examen.
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

    public function test_crear_examen_asigna_grupos_y_solo_habilita_estudiantes_activos(): void
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
            2,
            DB::table('examen_estudiante')->where('id_examen', $examId)->count()
        );

        $withdrawnStudentId = DB::table('grupo_estudiante')
            ->where('id_grupo', $this->grupoPropioId)
            ->where('estado', RecordStatus::INACTIVE)
            ->value('id_estudiante');

        $this->assertDatabaseMissing('examen_estudiante', [
            'id_examen' => $examId,
            'id_estudiante' => $withdrawnStudentId,
        ]);
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

    public function test_endpoint_reemplaza_grupos_y_nomina_en_una_transaccion(): void
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
            1,
            DB::table('examen_estudiante')->where('id_examen', $exam->id_examen)->count()
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

    public function test_rechaza_grupo_sin_nomina_activa(): void
    {
        $this->assertGroupIsRejected($this->groupWithoutRosterId);
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
            ->where('estado', RecordStatus::ACTIVE)
            ->value('id_estudiante');

        DB::table('grupo_estudiante')->insert([
            'id_grupo' => $otherGroup->id_grupo,
            'id_estudiante' => $studentId,
            'estado' => RecordStatus::ACTIVE,
        ]);

        $exam = $this->createExam();
        $this->postJson("/api/examenes/{$exam->id_examen}/grupos", [
            'grupos' => [$this->grupoPropioId, $otherGroup->id_grupo],
        ])->assertUnprocessable()->assertJsonValidationErrors('grupos');

        $this->assertSame(0, DB::table('grupo_examen')->count());
        $this->assertSame(0, DB::table('examen_estudiante')->count());
    }

    public function test_un_reemplazo_invalido_conserva_grupo_y_nomina_anteriores(): void
    {
        $exam = $this->createExam();

        $this->postJson("/api/examenes/{$exam->id_examen}/grupos", [
            'grupos' => [$this->grupoPropioId],
        ])->assertOk();

        $originalRoster = DB::table('examen_estudiante')
            ->where('id_examen', $exam->id_examen)
            ->orderBy('id_estudiante')
            ->pluck('id_estudiante')
            ->all();

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
        $this->assertSame(
            $originalRoster,
            DB::table('examen_estudiante')
                ->where('id_examen', $exam->id_examen)
                ->orderBy('id_estudiante')
                ->pluck('id_estudiante')
                ->all()
        );
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
