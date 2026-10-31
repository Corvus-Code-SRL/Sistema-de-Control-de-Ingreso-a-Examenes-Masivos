<?php

namespace Tests\Feature\Incidents;

use App\Models\Exam;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsExamSituations;
use Tests\TestCase;

class ExamSituationWorkspaceTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsExamSituations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedExamSituations();
    }

    private function contextUrl(): string
    {
        return "/api/situaciones/examenes/{$this->situationExam->id_examen}/contexto";
    }

    public function testCatalogOnlyIncludesAuthorizedExamsInSelectedState(): void
    {
        $this->createExam(['estado' => Exam::EN_CURSO, 'id_usuario_docente' => $this->otroDocenteId]);
        $finished = $this->createExam(['estado' => Exam::FINALIZADO]);
        $this->createExam();

        $this->getJson('/api/situaciones/examenes')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_examen', (int) $this->situationExam->id_examen);
        $this->getJson('/api/situaciones/examenes?estado=FINALIZADO')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_examen', (int) $finished->id_examen);
    }

    public function testCatalogPaginatesAndDoesNotAcceptAnotherPageSize(): void
    {
        for ($index = 0; $index < 20; $index++) {
            $this->createExam(['estado' => Exam::EN_CURSO]);
        }

        $this->getJson('/api/situaciones/examenes?per_page=100')->assertOk()
            ->assertJsonCount(20, 'data')->assertJsonPath('meta.total', 21);
        $this->getJson('/api/situaciones/examenes?page=2')->assertOk()->assertJsonCount(1, 'data');
    }

    public function testContextHasPresentStudentRoomsAndServerTimeWithoutPrivateFields(): void
    {
        $response = $this->getJson($this->contextUrl())->assertOk()
            ->assertJsonPath('data.ambiente_control', null)
            ->assertJsonCount(2, 'data.ambientes')->assertJsonCount(1, 'data.estudiantes')
            ->assertJsonPath('data.estudiantes.0.id_estudiante', (int) $this->situationStudent->id_estudiante)
            ->assertJsonPath('data.estudiantes.0.ambiente.id_ambiente', $this->aulaId)
            ->assertJsonPath('data.estudiantes.0.situaciones.identidad_observada', false)
            ->assertJsonStructure(['data' => [
                'hora_servidor', 'paginacion' => ['current_page', 'last_page', 'total'],
            ]]);

        $student = $response->json('data.estudiantes.0');
        $this->assertArrayNotHasKey('ci', $student);
        $this->assertArrayNotHasKey('correo', $student);
    }

    public function testAbsentStudentIsNotOfferedForRegistration(): void
    {
        DB::table('examen_estudiante')->where('id_examen', $this->situationExam->id_examen)
            ->update([
                'estado_ingreso' => 'NO_INGRESO', 'hora_ingreso' => null,
                'id_usuario_controlador' => null, 'registrado_en' => null,
            ]);

        $this->getJson($this->contextUrl())->assertOk()->assertJsonCount(0, 'data.estudiantes');
    }

    public function testSearchUsesSisAndAccentsAndTreatsPercentAsLiteral(): void
    {
        $this->situationStudent->update(['nombre' => 'José', 'apellido_paterno' => 'Ávila']);
        $this->getJson($this->contextUrl() . '?q=jose')->assertOk()->assertJsonCount(1, 'data.estudiantes');
        $this->getJson($this->contextUrl() . '?q=' . $this->situationStudent->cod_sis)
            ->assertOk()->assertJsonCount(1, 'data.estudiantes');
        $this->getJson($this->contextUrl() . '?q=%25')->assertOk()->assertJsonCount(0, 'data.estudiantes');
    }

    public function testAssistantOnlySeesAssignedRoomButCanChooseOtherExamRoomsAsDestination(): void
    {
        $this->actAsSituationAssistant($this->aulaId);
        $this->getJson('/api/situaciones/examenes')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson($this->contextUrl())->assertOk()
            ->assertJsonPath('data.ambiente_control', $this->aulaId)
            ->assertJsonCount(2, 'data.ambientes')->assertJsonCount(1, 'data.estudiantes');
        $this->getJson($this->contextUrl() . '?id_ambiente=' . $this->otraAulaId)->assertForbidden();

        $this->postJson($this->situationUrl(), $this->situationPayload())->assertCreated();
        $this->getJson($this->contextUrl())->assertOk()->assertJsonCount(0, 'data.estudiantes');
        $this->getJson($this->contextUrl() . '?id_estudiante=' . $this->situationStudent->id_estudiante)
            ->assertOk()->assertJsonCount(0, 'data.estudiantes');
    }

    public function testAmbiguousAssistantAssignmentIsNotListed(): void
    {
        $this->actAsSituationAssistant(null);
        $this->getJson('/api/situaciones/examenes')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson($this->contextUrl())->assertForbidden();
    }

    public function testSingleRoomAssignmentWithoutExplicitRoomRemainsSupported(): void
    {
        $this->situationExam->classrooms()->detach($this->otraAulaId);
        $this->actAsSituationAssistant(null);
        $this->getJson('/api/situaciones/examenes')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson($this->contextUrl())->assertOk()->assertJsonPath('data.ambiente_control', $this->aulaId);
    }

    public function testOtherTeacherAndGuestCannotReadTheContext(): void
    {
        $this->actingAs(User::query()->findOrFail($this->otroDocenteId));
        $this->getJson($this->contextUrl())->assertForbidden();
        $this->getJson('/api/situaciones/examenes')->assertOk()->assertJsonCount(0, 'data');
        $this->actAsGuest();
        $this->getJson($this->contextUrl())->assertUnauthorized();
        $this->getJson('/api/situaciones/examenes')->assertUnauthorized();
    }

    public function testBothMarksSurviveMoreThanOnePageOfHistory(): void
    {
        $this->postJson($this->situationUrl(), $this->situationPayload())->assertCreated();
        for ($index = 0; $index < 21; $index++) {
            $this->createSituationFixture(['id_ambiente_origen' => $this->otraAulaId]);
        }

        $this->getJson($this->contextUrl())->assertOk()
            ->assertJsonPath('data.estudiantes.0.ambiente.id_ambiente', $this->otraAulaId)
            ->assertJsonPath('data.estudiantes.0.situaciones.identidad_observada', true)
            ->assertJsonPath(
                'data.estudiantes.0.situaciones.ultimo_cambio_ambiente.ambiente_origen.id_ambiente',
                $this->aulaId
            );
        $this->assertDatabaseHas('examen_estudiante', [
            'id_examen' => $this->situationExam->id_examen,
            'id_estudiante' => $this->situationStudent->id_estudiante,
            'hora_ingreso' => '08:00:00', 'estado_ingreso' => 'INGRESO',
        ]);
    }

    public function testFinishedExamCanBeReadButNotChanged(): void
    {
        $this->situationExam->update(['estado' => Exam::FINALIZADO]);
        $this->getJson($this->contextUrl())->assertOk()->assertJsonPath('data.examen.estado', Exam::FINALIZADO);
        $this->postJson($this->situationUrl(), $this->situationPayload())->assertStatus(409);
        $this->situationExam->update(['estado' => Exam::PROGRAMADO]);
        $this->getJson($this->contextUrl())->assertStatus(409);
    }

    public function testValidatesFiltersAndBoundsRouteIdentifiers(): void
    {
        $this->getJson('/api/situaciones/examenes?estado=PROGRAMADO&page=0')->assertStatus(422)
            ->assertJsonValidationErrors(['estado', 'page']);
        $this->getJson($this->contextUrl() . '?page=0&id_estudiante=-1&q=' . str_repeat('x', 51))
            ->assertStatus(422)->assertJsonValidationErrors(['page', 'id_estudiante', 'q']);
        $this->getJson('/api/situaciones/examenes/1234567890/contexto')->assertNotFound();
        $this->getJson('/api/situaciones/examenes/0/contexto')->assertStatus(422);
    }
}