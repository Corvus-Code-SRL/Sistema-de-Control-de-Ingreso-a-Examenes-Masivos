<?php

namespace Tests\Feature\Incidents;

use App\Models\Exam;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\IncidentTypeSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\AssignsIncidentRole;
use Tests\Concerns\SeedsExamSituations;
use Tests\TestCase;

/**
 * HU-042: acceso «Reportar incidencia» del menú. Lista solo exámenes En curso, busca estudiantes
 * presentes por SIS, nombre o CI y no depende de los endpoints ni de la función de HU-057.
 */
class InProgressIncidentWorkspaceTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsExamSituations;
    use AssignsIncidentRole;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedExamSituations();
        $this->seed(IncidentTypeSeeder::class);
        Storage::fake('local');
    }

    private function examsUrl(): string
    {
        return '/api/incidencias/examenes';
    }

    private function contextUrl(?Exam $exam = null): string
    {
        return '/api/incidencias/examenes/' . ($exam ?? $this->situationExam)->id_examen . '/contexto';
    }

    private function reportUrl(): string
    {
        return str_replace('/situaciones', '/incidencias', $this->situationUrl());
    }

    private function payload(): array
    {
        return [
            'id_tipo_falta' => DB::table('tipo_falta')->where('nombre', 'Material no permitido')->value('id_falta'),
            'descripcion' => 'Usó un celular durante el examen sin autorización.',
            'id_ambiente' => $this->aulaId,
            'retirar' => false,
        ];
    }

    private function actAsRole(string $roleName): void
    {
        DB::table('usuario_rol')->where('id_usuario', $this->otroDocenteId)->update([
            'id_rol' => Role::query()->where('nombre_rol', $roleName)->value('id_rol'),
        ]);
        $this->actingAs(User::query()->findOrFail($this->otroDocenteId));
    }

    public function testListsOnlyInProgressExamsOfTheTeacher(): void
    {
        $scheduled = $this->createExam(['estado' => Exam::EN_INGRESO], [$this->aulaId]);
        $finished = $this->createExam(['estado' => Exam::FINALIZADO], [$this->aulaId]);

        $ids = collect($this->getJson($this->examsUrl())->assertOk()->json('data'))->pluck('id_examen')->all();

        $this->assertSame([$this->situationExam->id_examen], $ids);
        $this->assertNotContains($scheduled->id_examen, $ids);
        $this->assertNotContains($finished->id_examen, $ids);
    }

    public function testAFinishedExamNoLongerAppearsInTheList(): void
    {
        $this->situationExam->update(['estado' => Exam::FINALIZADO]);

        $this->getJson($this->examsUrl())->assertOk()->assertJsonCount(0, 'data');
        $this->getJson($this->contextUrl())->assertStatus(409)
            ->assertJsonPath('message', 'Solo se pueden reportar incidencias con el examen en curso.');
    }

    public function testListAcceptsNoStateFilterBeyondInProgress(): void
    {
        $this->createExam(['estado' => Exam::EN_INGRESO], [$this->aulaId]);

        $this->getJson($this->examsUrl() . '?estado=EN_INGRESO')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.estado', Exam::EN_CURSO);
    }

    public function testContextSearchesBySisNameOrCiAndCountsIncidents(): void
    {
        $this->postJson($this->reportUrl(), $this->payload())->assertCreated();
        $student = $this->situationStudent->fresh();

        foreach ([$student->cod_sis, $student->nombre, $student->ci] as $term) {
            $this->getJson($this->contextUrl() . '?q=' . urlencode($term))->assertOk()
                ->assertJsonCount(1, 'data.estudiantes')
                ->assertJsonPath('data.estudiantes.0.id_estudiante', $student->id_estudiante)
                ->assertJsonPath('data.estudiantes.0.total_incidencias', 1);
        }

        $this->getJson($this->contextUrl() . '?q=zzzz-sin-coincidencia')->assertOk()
            ->assertJsonCount(0, 'data.estudiantes');
    }

    public function testExpelledStudentsAreExcludedFromTheList(): void
    {
        $this->postJson($this->reportUrl(), array_merge($this->payload(), ['retirar' => true]))->assertCreated();

        $this->getJson($this->contextUrl())->assertOk()->assertJsonCount(0, 'data.estudiantes');
    }

    public function testAssistantOnlySeesTheirRoomAndExams(): void
    {
        $this->actAsSituationAssistant($this->otraAulaId);

        $this->getJson($this->examsUrl())->assertOk()->assertJsonCount(1, 'data');
        $this->getJson($this->contextUrl())->assertOk()
            ->assertJsonPath('data.ambiente_control', $this->otraAulaId)
            ->assertJsonCount(0, 'data.estudiantes');
        $this->getJson($this->contextUrl() . '?id_ambiente=' . $this->aulaId)->assertForbidden();
    }

    public function testAnotherTeacherDoesNotSeeTheExam(): void
    {
        $this->actingAs(User::query()->findOrFail($this->otroDocenteId));

        $this->getJson($this->examsUrl())->assertOk()->assertJsonCount(0, 'data');
        $this->getJson($this->contextUrl())->assertForbidden();
    }

    public function testAdministratorAndGuestCannotUseTheAccess(): void
    {
        $this->actAsRole(Role::ADMINISTRADOR);
        $this->getJson($this->examsUrl())->assertForbidden();
        $this->getJson($this->contextUrl())->assertForbidden();

        $this->actAsGuest();
        $this->getJson($this->examsUrl())->assertUnauthorized();
    }

    public function testInvitedTeacherCompletesTheWholeFlow(): void
    {
        DB::table('usuario_rol')->where('id_usuario', $this->docenteId)->whereNull('fecha_fin')
            ->update(['fecha_fin' => now()]);
        DB::table('usuario')->where('id_usuario', $this->docenteId)->update(['cod_sis' => 'INV4821']);
        $this->assignRole($this->docenteId, Role::INVITADO);
        $this->actAsUserId($this->docenteId);

        $this->getJson($this->examsUrl())->assertOk()->assertJsonCount(1, 'data');
        $this->getJson($this->contextUrl())->assertOk()->assertJsonCount(1, 'data.estudiantes');
        $this->postJson($this->reportUrl(), $this->payload())->assertCreated();
    }

    /** HU-042 depende de su propia función de acceso (`incidents`), no de la de HU-057 (`teaching`). */
    public function testFlowWorksWithoutTheSituationsFunction(): void
    {
        config(['sciem.access.teaching' => []]);

        $this->getJson($this->examsUrl())->assertOk()->assertJsonCount(1, 'data');
        $this->getJson($this->contextUrl())->assertOk()->assertJsonCount(1, 'data.estudiantes');
        $this->postJson($this->reportUrl(), $this->payload())->assertCreated();

        $this->getJson('/api/situaciones/examenes')->assertForbidden();
        $this->postJson($this->situationUrl(), $this->situationPayload())->assertForbidden();
    }

    public function testFlowIsClosedWhenTheIncidentsFunctionIsRemoved(): void
    {
        config(['sciem.access.incidents' => []]);

        $this->getJson($this->examsUrl())->assertForbidden();
        $this->getJson($this->contextUrl())->assertForbidden();
        $this->postJson($this->reportUrl(), $this->payload())->assertForbidden();
    }

    public function testContextUsesThePollingLimiterAndTheListKeepsTheActionsOne(): void
    {
        $router = $this->app['router'];
        $context = $router->gatherRouteMiddleware(Route::getRoutes()->getByName('incidents.in-progress.context'));
        $list = $router->gatherRouteMiddleware(Route::getRoutes()->getByName('incidents.in-progress.exams'));

        $this->assertContains(ThrottleRequests::class . ':polling', $context);
        $this->assertNotContains(ThrottleRequests::class . ':api', $context);
        $this->assertContains(ThrottleRequests::class . ':api', $list);
    }
}
