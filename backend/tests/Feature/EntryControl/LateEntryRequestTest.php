<?php

namespace Tests\Feature\EntryControl;

use App\Exceptions\EntryControl\LateEntryException;
use App\Http\Controllers\EntryControl\LateEntryController;
use App\Models\Exam;
use App\Models\LateEntryRequest;
use App\Models\StudentExam;
use App\Services\EntryControl\EntryControlSnapshotService;
use App\Services\EntryControl\LateEntryService;
use App\Services\EntryControl\VerificationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsLateEntryExam;
use Tests\TestCase;

/**
 * HU-037, criterios 6 a 9 y 11: el auxiliar solicita, el docente autoriza o rechaza, y
 * ambos ven el estado de las solicitudes. El estudiante autorizado queda CON_RETRASO a la
 * hora de la solicitud y los totales de En curso lo reflejan.
 */
class LateEntryRequestTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsLateEntryExam;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedLateEntryExam();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** El auxiliar de 692B solicita por el segundo estudiante y devuelve el id. */
    private function requestSecondStudent(): int
    {
        $this->actAsUserId($this->assistantBId);

        return (int) $this->postJson($this->lateEntryUrl('solicitudes'), [
            'id_estudiante' => $this->secondStudentId,
            'ci' => $this->studentColumn($this->secondStudentId, 'ci'),
            'id_ambiente' => $this->otraAulaId,
        ])->assertCreated()->json('data.id_solicitud');
    }

    private function resolve(int $requestId, string $action)
    {
        return $this->postJson($this->lateEntryUrl("solicitudes/{$requestId}/{$action}"));
    }

    private function inProgress()
    {
        $this->actAsTeacher($this->docenteId);

        return $this->getJson("/api/examenes/{$this->exam->id_examen}/en-curso")->assertOk();
    }

    public function test_el_auxiliar_envia_la_solicitud_a_las_10_12(): void
    {
        $this->travelToLocal('10:12');
        $this->actAsUserId($this->assistantBId);

        $this->postJson($this->lateEntryUrl('solicitudes'), [
            'id_estudiante' => $this->secondStudentId,
            'ci' => $this->studentColumn($this->secondStudentId, 'ci'),
            'id_ambiente' => $this->otraAulaId,
        ])
            ->assertCreated()
            ->assertJsonPath('mensaje', LateEntryController::REQUESTED_MESSAGE)
            ->assertJsonPath('data.estado', LateEntryRequest::PENDIENTE)
            ->assertJsonPath('data.hora_solicitud', '10:12')
            ->assertJsonPath('data.estudiante.id_estudiante', $this->secondStudentId)
            ->assertJsonPath('data.ambiente.nro_aula', '692B')
            ->assertJsonPath('data.solicitante.nombre_completo', 'Auxiliar 202498002')
            ->assertJsonPath('data.resolutor', null)
            ->assertJsonPath('data.resuelto_en', null);

        $this->assertNull($this->entryOf($this->secondStudentId));
    }

    public function test_el_docente_ve_la_solicitud_pendiente_con_estudiante_ambiente_hora_y_solicitante(): void
    {
        $this->travelToLocal('10:12');
        $requestId = $this->requestSecondStudent();

        $this->actAsTeacher($this->docenteId);
        $this->getJson($this->lateEntryUrl('solicitudes'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_solicitud', $requestId)
            ->assertJsonPath('data.0.estado', LateEntryRequest::PENDIENTE)
            ->assertJsonPath('data.0.hora_solicitud', '10:12')
            ->assertJsonPath('data.0.ambiente.nro_aula', '692B')
            ->assertJsonPath('data.0.solicitante.nombre_completo', 'Auxiliar 202498002');
    }

    public function test_autorizar_registra_con_retraso_a_la_hora_de_la_solicitud_y_actualiza_totales(): void
    {
        $before = $this->inProgress()->json('data.totales');

        $this->travelToLocal('10:12');
        $requestId = $this->requestSecondStudent();

        $this->travelToLocal('10:20');
        $this->actAsTeacher($this->docenteId);
        $this->resolve($requestId, 'autorizar')
            ->assertOk()
            ->assertJsonPath('mensaje', LateEntryController::APPROVED_MESSAGE)
            ->assertJsonPath('data.estado', LateEntryRequest::AUTORIZADA)
            ->assertJsonPath('data.resolutor.nombre_completo', 'Ana Rojas');

        $after = $this->inProgress()->json('data');
        $student = collect($after['estudiantes'])->firstWhere('id_estudiante', $this->secondStudentId);

        $this->assertSame(StudentExam::CON_RETRASO, $student['estado_ingreso']);
        $this->assertSame('10:12', $student['hora_ingreso']);
        $this->assertSame('692B', $student['ambiente']);
        $this->assertSame($before['con_retraso'] + 1, $after['totales']['con_retraso']);
        $this->assertSame($before['ausentes'] - 1, $after['totales']['ausentes']);
    }

    public function test_el_auxiliar_ve_su_solicitud_autorizada_o_rechazada_sin_recargar(): void
    {
        $authorizedId = $this->requestSecondStudent();
        $this->actAsUserId($this->assistantAId);
        $rejectedId = (int) $this->postJson($this->lateEntryUrl('solicitudes'), [
            'id_estudiante' => $this->firstStudentId,
            'id_ambiente' => $this->aulaId,
        ])->assertCreated()->json('data.id_solicitud');

        $this->actAsTeacher($this->docenteId);
        $this->resolve($authorizedId, 'autorizar')->assertOk();
        $this->resolve($rejectedId, 'rechazar')
            ->assertOk()
            ->assertJsonPath('mensaje', LateEntryController::REJECTED_MESSAGE)
            ->assertJsonPath('data.estado', LateEntryRequest::RECHAZADA);

        $this->actAsUserId($this->assistantBId);
        $this->getJson($this->lateEntryUrl('solicitudes'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_solicitud', $authorizedId)
            ->assertJsonPath('data.0.estado', LateEntryRequest::AUTORIZADA);

        $this->actAsUserId($this->assistantAId);
        $this->getJson($this->lateEntryUrl('solicitudes'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.estado', LateEntryRequest::RECHAZADA);
    }

    public function test_rechazar_deja_al_estudiante_ausente(): void
    {
        $requestId = $this->requestSecondStudent();

        $this->actAsTeacher($this->docenteId);
        $this->resolve($requestId, 'rechazar')->assertOk();

        $student = collect($this->inProgress()->json('data.estudiantes'))
            ->firstWhere('id_estudiante', $this->secondStudentId);

        $this->assertSame(StudentExam::NO_INGRESO, $student['estado_ingreso']);
        $this->assertNull($this->entryOf($this->secondStudentId));
    }

    public function test_cada_operacion_queda_en_la_bitacora(): void
    {
        $requestId = $this->requestSecondStudent();
        $this->actAsTeacher($this->docenteId);
        $this->resolve($requestId, 'autorizar')->assertOk();

        foreach ([LateEntryService::REQUEST_ACTION, LateEntryService::AUTHORIZE_ACTION] as $action) {
            $this->assertSame(1, DB::table('log')
                ->join('accion', 'accion.id_accion', '=', 'log.id_accion')
                ->where('accion.operacion', $action)
                ->count(), $action);
        }
    }

    public function test_la_solicitud_rechazada_por_la_verificacion_lleva_el_veredicto(): void
    {
        $this->actAsUserId($this->assistantBId);

        $this->postJson($this->lateEntryUrl('solicitudes'), [
            'id_estudiante' => $this->secondStudentId,
            'ci' => '1',
            'id_ambiente' => $this->otraAulaId,
        ])
            ->assertStatus(422)
            ->assertJsonPath('veredicto', VerificationService::CI_MISMATCH)
            ->assertJsonPath('message', VerificationService::CI_MISMATCH_MESSAGE);
        $this->postJson($this->lateEntryUrl('solicitudes'), [
            'id_estudiante' => $this->firstStudentId,
            'id_ambiente' => $this->otraAulaId,
        ])
            ->assertStatus(422)
            ->assertJsonPath('veredicto', VerificationService::WRONG_ROOM);

        $this->assertSame(0, LateEntryRequest::query()->count());
    }

    public function test_valida_los_datos_de_la_solicitud(): void
    {
        $this->actAsUserId($this->assistantBId);

        $this->postJson($this->lateEntryUrl('solicitudes'), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['id_estudiante', 'id_ambiente']);
        $this->postJson($this->lateEntryUrl('solicitudes'), [
            'id_estudiante' => 'x',
            'cod_sis' => '202400001',
            'ci' => 'ABC',
            'id_ambiente' => 0,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['id_estudiante', 'cod_sis', 'ci', 'id_ambiente']);
    }

    public function test_conflictos_pendiente_duplicado_y_resuelta(): void
    {
        $requestId = $this->requestSecondStudent();

        $this->postJson($this->lateEntryUrl('solicitudes'), [
            'id_estudiante' => $this->secondStudentId,
            'id_ambiente' => $this->otraAulaId,
        ])
            ->assertStatus(409)
            ->assertJsonPath('veredicto', LateEntryException::PENDING_REQUEST)
            ->assertJsonPath('message', LateEntryException::PENDING_REQUEST_MESSAGE);

        $this->actAsTeacher($this->docenteId);
        $this->resolve($requestId, 'autorizar')->assertOk();
        $this->resolve($requestId, 'autorizar')
            ->assertStatus(409)
            ->assertJsonPath('veredicto', LateEntryException::REQUEST_RESOLVED);
        $this->resolve($requestId, 'rechazar')
            ->assertStatus(409)
            ->assertJsonPath('veredicto', LateEntryException::REQUEST_RESOLVED);

        $this->actAsUserId($this->assistantBId);
        $this->postJson($this->lateEntryUrl('solicitudes'), [
            'id_estudiante' => $this->secondStudentId,
            'id_ambiente' => $this->otraAulaId,
        ])
            ->assertStatus(409)
            ->assertJsonPath('veredicto', VerificationService::DUPLICATE);
    }

    public function test_un_examen_finalizado_no_admite_solicitudes_ni_resoluciones(): void
    {
        $requestId = $this->requestSecondStudent();
        $this->setLateEntryExamState(Exam::FINALIZADO);

        $this->postJson($this->lateEntryUrl('solicitudes'), [
            'id_estudiante' => $this->firstStudentId,
            'id_ambiente' => $this->otraAulaId,
        ])
            ->assertStatus(409)
            ->assertJsonPath('veredicto', LateEntryException::EXAM_NOT_IN_PROGRESS);

        $this->actAsTeacher($this->docenteId);
        foreach (['autorizar', 'rechazar'] as $action) {
            $this->resolve($requestId, $action)
                ->assertStatus(409)
                ->assertJsonPath('veredicto', LateEntryException::EXAM_NOT_IN_PROGRESS);
        }

        $this->getJson($this->lateEntryUrl('solicitudes'))->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_un_examen_que_no_inicio_no_lista_solicitudes(): void
    {
        $this->setLateEntryExamState(Exam::EN_INGRESO);
        $this->actAsTeacher($this->docenteId);

        $this->getJson($this->lateEntryUrl('solicitudes'))->assertStatus(409);
    }

    public function test_permisos_de_cada_rol(): void
    {
        $requestId = $this->requestSecondStudent();
        $payload = ['id_estudiante' => $this->firstStudentId, 'id_ambiente' => $this->aulaId];

        $this->resolve($requestId, 'autorizar')->assertForbidden();
        $this->postJson($this->lateEntryUrl('solicitudes'), $payload)
            ->assertForbidden()
            ->assertJsonPath('message', EntryControlSnapshotService::FOREIGN_ROOM_MESSAGE);

        $this->actAsTeacher($this->docenteId);
        $this->postJson($this->lateEntryUrl('solicitudes'), $payload)->assertForbidden();

        $this->actAsTeacher($this->otroDocenteId);
        $this->resolve($requestId, 'autorizar')->assertForbidden();
        $this->resolve($requestId, 'rechazar')->assertForbidden();
        $this->getJson($this->lateEntryUrl('solicitudes'))->assertForbidden();

        $notEnabledId = '55555555-5555-4555-8555-00000000000c';
        $this->createLateEntryAssistant($notEnabledId, '202498003', null, false);
        $this->actAsUserId($notEnabledId);
        $this->postJson($this->lateEntryUrl('solicitudes'), $payload)->assertForbidden();

        $this->assertSame(LateEntryRequest::PENDIENTE, LateEntryRequest::query()->findOrFail($requestId)->estado);
    }

    public function test_una_solicitud_de_otro_examen_no_existe_en_este(): void
    {
        $requestId = $this->requestSecondStudent();
        $otherExam = $this->createExam(['estado' => Exam::EN_CURSO], [$this->aulaId]);

        $this->actAsTeacher($this->docenteId);
        $this->postJson($this->lateEntryUrl("solicitudes/{$requestId}/autorizar", $otherExam))->assertNotFound();
        $this->resolve(999999999, 'rechazar')->assertNotFound();
        $this->postJson($this->lateEntryUrl('solicitudes/abc/autorizar'))->assertNotFound();

        $this->assertSame(LateEntryRequest::PENDIENTE, LateEntryRequest::query()->findOrFail($requestId)->estado);
    }

    public function test_sin_sesion_responde_401(): void
    {
        $requestId = $this->requestSecondStudent();
        $this->actAsGuest();

        $this->getJson($this->lateEntryUrl('solicitudes'))->assertUnauthorized();
        $this->postJson($this->lateEntryUrl('solicitudes'), [])->assertUnauthorized();
        $this->resolve($requestId, 'autorizar')->assertUnauthorized();
        $this->resolve($requestId, 'rechazar')->assertUnauthorized();
    }

    private function insertResolvedRequests(int $amount): void
    {
        $this->enrollStudents($this->grupoPropioId, $amount);
        $studentIds = DB::table('grupo_estudiante')
            ->where('id_grupo', $this->grupoPropioId)
            ->whereNotIn('id_estudiante', LateEntryRequest::query()->pluck('id_estudiante'))
            ->pluck('id_estudiante')
            ->take($amount);

        foreach ($studentIds as $studentId) {
            DB::table('solicitud_ingreso_retraso')->insert([
                'id_examen' => $this->exam->id_examen,
                'id_estudiante' => $studentId,
                'id_ambiente' => $this->aulaId,
                'id_usuario_solicitante' => $this->assistantAId,
                'id_usuario_resolutor' => $this->docenteId,
                'estado' => LateEntryRequest::RECHAZADA,
                'solicitado_en' => '2026-10-31 14:12:00+00',
                'resuelto_en' => '2026-10-31 14:20:00+00',
            ]);
        }
    }

    private function listQueryCount(): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->getJson($this->lateEntryUrl('solicitudes'))->assertOk();

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    public function test_la_cantidad_de_consultas_del_listado_no_crece_con_las_solicitudes(): void
    {
        $this->actAsTeacher($this->docenteId);
        $this->insertResolvedRequests(1);
        $few = $this->listQueryCount();

        $this->insertResolvedRequests(6);
        $many = $this->listQueryCount();

        $this->assertSame(7, LateEntryRequest::query()->count());
        $this->assertSame($few, $many, 'El listado hace una consulta por solicitud.');
    }
}
