<?php

namespace Tests\Feature\EntryControl;

use App\Models\LateEntryRequest;
use App\Models\Role;
use App\Models\StudentExam;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\AssignsIncidentRole;
use Tests\Concerns\SeedsLateEntryExam;
use Tests\TestCase;

/**
 * HU-037 con el docente Invitado (HU-004): ejerce la docencia, así que opera el ingreso con
 * retraso de los exámenes que dicta igual que un Docente. En un examen ajeno recibe 403.
 */
class LateEntryInvitedTeacherTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsLateEntryExam;
    use AssignsIncidentRole;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedLateEntryExam();
        $this->makeInvited($this->docenteId, 'INV4821');
    }

    private function makeInvited(string $userId, string $code): void
    {
        DB::table('usuario_rol')->where('id_usuario', $userId)->whereNull('fecha_fin')
            ->update(['fecha_fin' => now()]);
        DB::table('usuario')->where('id_usuario', $userId)->update(['cod_sis' => $code]);
        $this->assignRole($userId, Role::INVITADO);
    }

    private function requestSecondStudent(): int
    {
        $this->actAsUserId($this->assistantBId);

        return (int) $this->postJson($this->lateEntryUrl('solicitudes'), [
            'id_estudiante' => $this->secondStudentId,
            'id_ambiente' => $this->otraAulaId,
        ])->assertCreated()->json('data.id_solicitud');
    }

    public function test_el_invitado_dueno_verifica_busca_y_lista(): void
    {
        $this->actAsUserId($this->docenteId);

        $this->postJson($this->lateEntryUrl('verificar'), [
            'cod_sis' => $this->studentColumn($this->secondStudentId, 'cod_sis'),
        ])->assertOk()->assertJsonPath('data.autorizado', true);
        $this->getJson($this->lateEntryUrl('buscar') . '?sis=' . substr(
            $this->studentColumn($this->secondStudentId, 'cod_sis'),
            0,
            4
        ))->assertOk();
        $this->getJson($this->lateEntryUrl('solicitudes'))->assertOk();
    }

    public function test_el_invitado_dueno_registra_directamente(): void
    {
        $this->actAsUserId($this->docenteId);

        $this->postJson($this->lateEntryUrl('registrar'), [
            'id_estudiante' => $this->secondStudentId,
            'id_ambiente' => $this->otraAulaId,
        ])->assertCreated();
        $this->assertSame(StudentExam::CON_RETRASO, $this->entryOf($this->secondStudentId)->estado_ingreso);
    }

    public function test_el_invitado_dueno_autoriza_y_rechaza(): void
    {
        $requestId = $this->requestSecondStudent();
        $this->actAsUserId($this->docenteId);
        $this->postJson($this->lateEntryUrl("solicitudes/{$requestId}/autorizar"))->assertOk();
        $this->assertSame(StudentExam::CON_RETRASO, $this->entryOf($this->secondStudentId)->estado_ingreso);

        $this->actAsUserId($this->assistantAId);
        $otherId = (int) $this->postJson($this->lateEntryUrl('solicitudes'), [
            'id_estudiante' => $this->firstStudentId,
            'id_ambiente' => $this->aulaId,
        ])->assertCreated()->json('data.id_solicitud');
        $this->actAsUserId($this->docenteId);
        $this->postJson($this->lateEntryUrl("solicitudes/{$otherId}/rechazar"))->assertOk();
        $this->assertSame(LateEntryRequest::RECHAZADA, LateEntryRequest::query()->findOrFail($otherId)->estado);
    }

    public function test_un_invitado_que_no_dicta_el_examen_recibe_403(): void
    {
        $requestId = $this->requestSecondStudent();
        $this->makeInvited($this->otroDocenteId, 'INV4822');
        $this->actAsUserId($this->otroDocenteId);

        $this->postJson($this->lateEntryUrl('verificar'), [
            'cod_sis' => $this->studentColumn($this->secondStudentId, 'cod_sis'),
        ])->assertForbidden();
        $this->getJson($this->lateEntryUrl('buscar') . '?nombre=ana')->assertForbidden();
        $this->getJson($this->lateEntryUrl('solicitudes'))->assertForbidden();
        $this->postJson($this->lateEntryUrl('registrar'), [
            'id_estudiante' => $this->secondStudentId,
            'id_ambiente' => $this->otraAulaId,
        ])->assertForbidden();
        $this->postJson($this->lateEntryUrl("solicitudes/{$requestId}/autorizar"))->assertForbidden();
        $this->postJson($this->lateEntryUrl("solicitudes/{$requestId}/rechazar"))->assertForbidden();

        $this->assertNull($this->entryOf($this->secondStudentId));
        $this->assertSame(LateEntryRequest::PENDIENTE, LateEntryRequest::query()->findOrFail($requestId)->estado);
    }
}
