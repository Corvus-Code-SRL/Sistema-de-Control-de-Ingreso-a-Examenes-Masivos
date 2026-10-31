<?php

namespace Tests\Feature\EntryControl;

use App\Exceptions\EntryControl\LateEntryException;
use App\Http\Controllers\EntryControl\LateEntryController;
use App\Models\Exam;
use App\Models\StudentExam;
use App\Services\EntryControl\EntryControlSnapshotService;
use App\Services\EntryControl\LateEntryAccessService;
use App\Services\EntryControl\VerificationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsLateEntryExam;
use Tests\TestCase;

/**
 * HU-037, criterios 10 y 11: el docente registra directamente, sin solicitud, en el
 * ambiente del examen que elige. Una solicitud pendiente del estudiante lo impide.
 */
class LateEntryDirectRegistrationTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsLateEntryExam;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedLateEntryExam();
        $this->actAsTeacher($this->docenteId);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @param array<string, string|int|null> $overrides */
    private function register(int $studentId, array $overrides = [])
    {
        return $this->postJson($this->lateEntryUrl('registrar'), array_merge([
            'id_estudiante' => $studentId,
            'ci' => $this->studentColumn($studentId, 'ci'),
            'id_ambiente' => $this->otraAulaId,
        ], $overrides));
    }

    public function test_registra_con_retraso_a_la_hora_actual_sin_solicitud(): void
    {
        $this->travelToLocal('10:30');

        $this->register($this->secondStudentId)
            ->assertCreated()
            ->assertJsonPath('mensaje', LateEntryController::REGISTERED_MESSAGE)
            ->assertJsonPath('data.estado_ingreso', StudentExam::CON_RETRASO)
            ->assertJsonPath('data.hora_ingreso', '10:30')
            ->assertJsonPath('data.id_ambiente', $this->otraAulaId);

        $student = collect($this->getJson("/api/examenes/{$this->exam->id_examen}/en-curso")
            ->assertOk()
            ->json('data.estudiantes'))
            ->firstWhere('id_estudiante', $this->secondStudentId);

        $this->assertSame(StudentExam::CON_RETRASO, $student['estado_ingreso']);
        $this->assertSame('10:30', $student['hora_ingreso']);
        $this->assertSame($this->docenteId, $this->entryOf($this->secondStudentId)->id_usuario_controlador);
        $this->assertSame(0, DB::table('solicitud_ingreso_retraso')->count());
    }

    public function test_el_docente_elige_otro_ambiente_del_examen(): void
    {
        $this->register($this->secondStudentId, ['id_ambiente' => $this->aulaId])->assertCreated();

        $this->assertSame($this->aulaId, (int) $this->entryOf($this->secondStudentId)->id_ambiente);
    }

    public function test_una_solicitud_pendiente_impide_el_registro_directo(): void
    {
        $this->actAsUserId($this->assistantBId);
        $this->postJson($this->lateEntryUrl('solicitudes'), [
            'id_estudiante' => $this->secondStudentId,
            'id_ambiente' => $this->otraAulaId,
        ])->assertCreated();

        $this->actAsTeacher($this->docenteId);
        $this->register($this->secondStudentId)
            ->assertStatus(409)
            ->assertJsonPath('veredicto', LateEntryException::PENDING_REQUEST)
            ->assertJsonPath('message', LateEntryException::PENDING_REQUEST_MESSAGE);

        $this->assertNull($this->entryOf($this->secondStudentId));
    }

    public function test_un_estudiante_que_ya_ingreso_es_un_conflicto(): void
    {
        $this->insertEntry($this->secondStudentId, '10:01:00', $this->otraAulaId);

        $this->register($this->secondStudentId)
            ->assertStatus(409)
            ->assertJsonPath('veredicto', VerificationService::DUPLICATE)
            ->assertJsonPath('message', 'Ya ingresó a las 10:01 por el 692B');
    }

    public function test_un_examen_finalizado_responde_409(): void
    {
        $this->setLateEntryExamState(Exam::FINALIZADO);

        $this->register($this->secondStudentId)
            ->assertStatus(409)
            ->assertJsonPath('veredicto', LateEntryException::EXAM_NOT_IN_PROGRESS);

        $this->assertNull($this->entryOf($this->secondStudentId));
    }

    public function test_los_veredictos_negativos_responden_422_con_su_codigo(): void
    {
        $this->enrollStudents($this->grupoAjenoId, 1);
        $outsiderId = (int) DB::table('grupo_estudiante')->where('id_grupo', $this->grupoAjenoId)->value('id_estudiante');

        $this->register($this->secondStudentId, ['ci' => '1'])
            ->assertStatus(422)
            ->assertJsonPath('veredicto', VerificationService::CI_MISMATCH);
        $this->register($outsiderId)
            ->assertStatus(422)
            ->assertJsonPath('veredicto', VerificationService::NOT_IN_EXAM)
            ->assertJsonPath('message', VerificationService::NOT_IN_EXAM_MESSAGE);
        $this->register(999999999)
            ->assertStatus(422)
            ->assertJsonPath('veredicto', VerificationService::NOT_FOUND);
    }

    public function test_valida_los_datos_del_registro(): void
    {
        $this->postJson($this->lateEntryUrl('registrar'), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['id_estudiante', 'id_ambiente']);
        $this->register($this->secondStudentId, ['id_ambiente' => 'x', 'ci' => 'ABC'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['id_ambiente', 'ci']);
    }

    public function test_solo_el_docente_propietario_registra_en_un_ambiente_del_examen(): void
    {
        $this->register($this->secondStudentId, ['id_ambiente' => $this->aulaInactivaId])
            ->assertForbidden()
            ->assertJsonPath('message', EntryControlSnapshotService::ROOM_NOT_IN_EXAM_MESSAGE);

        $this->actAsTeacher($this->otroDocenteId);
        $this->register($this->secondStudentId)->assertForbidden();

        $this->actAsUserId($this->assistantBId);
        $this->register($this->secondStudentId)
            ->assertForbidden()
            ->assertJsonPath('message', LateEntryAccessService::TEACHER_ONLY_MESSAGE);

        $this->actAsGuest();
        $this->register($this->secondStudentId)->assertUnauthorized();

        $this->assertNull($this->entryOf($this->secondStudentId));
    }
}
