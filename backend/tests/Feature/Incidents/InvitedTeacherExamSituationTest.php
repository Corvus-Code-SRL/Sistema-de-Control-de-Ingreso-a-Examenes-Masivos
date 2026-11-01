<?php

namespace Tests\Feature\Incidents;

use App\Models\Exam;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\AssignsIncidentRole;
use Tests\Concerns\SeedsExamSituations;
use Tests\TestCase;

/**
 * El docente invitado es el responsable de sus exámenes: registra y consulta sus situaciones
 * excepcionales igual que un Docente, porque el acceso se decide por la función «teaching».
 */
class InvitedTeacherExamSituationTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsExamSituations;
    use AssignsIncidentRole;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedExamSituations();

        DB::table('usuario_rol')->where('id_usuario', $this->docenteId)->whereNull('fecha_fin')
            ->update(['fecha_fin' => now()]);
        DB::table('usuario')->where('id_usuario', $this->docenteId)->update(['cod_sis' => 'INV4821']);
        $this->assignRole($this->docenteId, Role::INVITADO);
        $this->actAsUserId($this->docenteId);
    }

    public function testInvitedOwnerRegistersASituation(): void
    {
        $this->postJson($this->situationUrl(), $this->situationPayload())
            ->assertCreated()
            ->assertJsonPath('data.registrante.id_usuario', $this->docenteId);

        $this->assertDatabaseHas('situacion_examen', [
            'id_examen' => $this->situationExam->id_examen,
            'id_usuario_registrante' => $this->docenteId,
            'tipo' => 'CAMBIO_AMBIENTE',
        ]);
    }

    public function testInvitedOwnerListsTheSituationsOfTheExam(): void
    {
        $this->createSituationFixture();

        $this->getJson($this->situationListUrl())->assertOk()->assertJsonCount(1, 'data');
    }

    public function testInvitedOwnerSeesTheExamInTheCatalogAndItsContext(): void
    {
        $examId = (int) $this->situationExam->id_examen;

        $this->getJson('/api/situaciones/examenes')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_examen', $examId);
        $this->getJson("/api/situaciones/examenes/{$examId}/contexto")->assertOk()
            ->assertJsonPath('data.ambiente_control', null);
    }

    public function testInvitedTeacherCannotRegisterInAnotherTeachersExam(): void
    {
        $this->situationExam->forceFill(['id_usuario_docente' => $this->otroDocenteId])->save();

        $this->postJson($this->situationUrl(), $this->situationPayload())->assertForbidden();
        $this->getJson('/api/situaciones/examenes')->assertOk()->assertJsonCount(0, 'data');
        $this->assertSame(0, DB::table('situacion_examen')->count());
    }

    public function testAdministratorStillCannotRegisterSituations(): void
    {
        DB::table('usuario_rol')->where('id_usuario', $this->otroDocenteId)->update([
            'id_rol' => Role::query()->where('nombre_rol', Role::ADMINISTRADOR)->value('id_rol'),
        ]);
        $this->actingAs(User::query()->findOrFail($this->otroDocenteId));

        $this->postJson($this->situationUrl(), $this->situationPayload())->assertForbidden();
        $this->getJson('/api/situaciones/examenes')->assertForbidden();
        $this->getJson($this->situationListUrl())->assertForbidden();
        $this->assertSame(Exam::EN_CURSO, $this->situationExam->fresh()->estado);
    }
}