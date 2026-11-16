<?php

namespace Tests\Feature\Exams\ReceivedInvitations;

use App\Models\Exam;
use App\Models\ExamInvitation;
use App\Models\User;
use App\Services\EntryControl\LateEntryAccessService;
use App\Services\Exams\ExamCollaboratorService;
use App\Services\Incidents\ExamSituationAccessService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\AssignsIncidentRole;
use Tests\Concerns\SeedsCollaboratorInvitations;
use Tests\Concerns\SeedsExamCatalog;
use Tests\TestCase;

/**
 * HU-027: el colaborador que aceptó la invitación es docente del examen en el control de ingreso (032), los
 * retrasos (037), las situaciones (042/057), el examen en curso (038), el reporte final (051) y el historial
 * (052). Una invitación pendiente, rechazada o sin efecto no da nada.
 */
class CollaboratorMembershipTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsExamCatalog;
    use AssignsIncidentRole;
    use SeedsCollaboratorInvitations;

    private Exam $exam;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedExamCatalog();
        $this->exam = $this->createExam(['nombre_examen' => 'Parcial común'], [$this->aulaId, $this->otraAulaId]);
        $this->exam->groups()->attach($this->grupoPropioId);
    }

    private function collaborator(): User
    {
        return User::findOrFail($this->otroDocenteId);
    }

    public function test_solo_la_invitacion_aceptada_hace_docente_del_examen(): void
    {
        $service = app(ExamCollaboratorService::class);

        $this->assertTrue($service->isTeacherOf($this->exam, $this->docenteId));
        $this->assertFalse($service->isTeacherOf($this->exam, $this->otroDocenteId));
        $this->assertFalse($service->isTeacherOf($this->exam, ''));

        foreach ([ExamInvitation::PENDIENTE, ExamInvitation::RECHAZADA, ExamInvitation::SIN_EFECTO] as $state) {
            $invitation = $this->createInvitation($this->exam, $this->otroDocenteId, $state);
            $this->assertFalse($service->isTeacherOf($this->exam, $this->otroDocenteId), $state);
            $invitation->delete();
        }

        $this->createInvitation($this->exam, $this->otroDocenteId, ExamInvitation::ACEPTADA);

        $this->assertTrue($service->isTeacherOf($this->exam, $this->otroDocenteId));
        $this->assertSame([$this->otroDocenteId], $service->collaboratorIds($this->exam->id_examen));
    }

    public function test_el_colaborador_ve_el_examen_en_el_control_de_ingreso_como_docente(): void
    {
        $this->exam->update(['estado' => Exam::EN_INGRESO]);
        $this->actAsTeacher($this->otroDocenteId);

        $this->getJson('/api/control-ingreso/examenes')->assertOk()->assertJsonCount(0, 'data');

        $this->createInvitation($this->exam, $this->otroDocenteId, ExamInvitation::ACEPTADA);

        $this->getJson('/api/control-ingreso/examenes')
            ->assertOk()
            ->assertJsonPath('data.0.id_examen', $this->exam->id_examen)
            ->assertJsonPath('data.0.rol_controlador', 'DOCENTE')
            ->assertJsonPath('data.0.id_ambiente_asignado', null);
    }

    public function test_el_colaborador_resuelve_retrasos_y_situaciones_como_docente(): void
    {
        $this->exam->update(['estado' => Exam::EN_CURSO]);

        try {
            app(LateEntryAccessService::class)->resolve($this->exam, $this->collaborator());
            $this->fail('Sin invitación aceptada no debería poder controlar el examen.');
        } catch (AuthorizationException $exception) {
            $this->addToAssertionCount(1);
        }

        $this->createInvitation($this->exam, $this->otroDocenteId, ExamInvitation::ACEPTADA);

        $this->assertSame(
            ['rol' => LateEntryAccessService::TEACHER, 'id_ambiente' => null],
            app(LateEntryAccessService::class)->resolve($this->exam, $this->collaborator())
        );
        $this->assertNull(app(ExamSituationAccessService::class)->roomScope($this->exam, $this->collaborator()));
        $this->assertContains(
            $this->exam->id_examen,
            app(ExamSituationAccessService::class)->visibleExams($this->collaborator())->pluck('id_examen')->all()
        );
    }

    public function test_el_colaborador_ve_el_reporte_final_y_el_historial(): void
    {
        $this->exam->update(['estado' => Exam::FINALIZADO]);
        $this->actAsTeacher($this->otroDocenteId);

        $this->getJson("/api/examenes/{$this->exam->id_examen}/reporte-final")->assertForbidden();
        $this->getJson("/api/historial/examenes/{$this->exam->id_examen}")->assertForbidden();
        $this->getJson('/api/historial/examenes')->assertOk()->assertJsonPath('data.total', 0);

        $this->createInvitation($this->exam, $this->otroDocenteId, ExamInvitation::ACEPTADA);

        $this->getJson("/api/examenes/{$this->exam->id_examen}/reporte-final")->assertOk();
        $this->getJson("/api/historial/examenes/{$this->exam->id_examen}")->assertOk();
        $this->getJson('/api/historial/examenes')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.examenes.0.id_examen', $this->exam->id_examen)
            ->assertJsonPath('data.materias.0.id_materia', $this->calculoId);
    }

    public function test_el_colaborador_no_inicia_ni_cancela_el_examen(): void
    {
        $this->createInvitation($this->exam, $this->otroDocenteId, ExamInvitation::ACEPTADA);
        $this->actAsTeacher($this->otroDocenteId);

        $this->postJson("/api/examenes/{$this->exam->id_examen}/cancelar")->assertForbidden();
        $this->assertSame(Exam::PROGRAMADO, $this->exam->fresh()->estado);
    }
}
