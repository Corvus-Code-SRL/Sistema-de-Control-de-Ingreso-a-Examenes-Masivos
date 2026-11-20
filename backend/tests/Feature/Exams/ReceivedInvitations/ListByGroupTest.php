<?php

namespace Tests\Feature\Exams\ReceivedInvitations;

use App\Models\Exam;
use App\Models\ExamInvitation;
use App\Services\EntryControl\RoomAssignmentService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\AssignsIncidentRole;
use Tests\Concerns\SeedsCollaboratorInvitations;
use Tests\Concerns\SeedsExamCatalog;
use Tests\TestCase;

/**
 * HU-026/027: el docente que invita puede pedir que, cuando el colaborador sume sus grupos, la lista del examen
 * se muestre separada por grupo (con su docente) en el control de ingreso y durante el examen. La nómina es la
 * misma: solo cambia cómo se muestra.
 */
class ListByGroupTest extends TestCase
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
        $this->exam = $this->createExam([], [$this->aulaId]);
        $this->exam->groups()->attach($this->grupoPropioId);
        $this->enrollStudents($this->grupoAjenoId, 3);
    }

    /** El colaborador (Luis Vargas) aceptó y sumó su grupo 3 al examen. */
    private function acceptWithGroup(bool $listByGroup): void
    {
        $this->createInvitation($this->exam, $this->otroDocenteId, ExamInvitation::ACEPTADA, [
            'lista_por_grupo' => $listByGroup,
        ]);
        $this->exam->groups()->attach($this->grupoAjenoId);
    }

    public function test_quien_invita_elige_la_lista_separada_solo_si_permite_grupos(): void
    {
        $this->postJson($this->invitationsUrl($this->exam), [
            'id_docente' => $this->otroDocenteId,
            'permite_agregar_grupos' => false,
            'lista_por_grupo' => true,
        ])->assertStatus(422)
            ->assertJsonPath(
                'errors.lista_por_grupo.0',
                'Solo puede separar la lista por grupo si permite agregar grupos.'
            );
        $this->assertSame(0, ExamInvitation::query()->count());

        $this->postJson($this->invitationsUrl($this->exam), [
            'id_docente' => $this->otroDocenteId,
            'permite_agregar_grupos' => true,
            'lista_por_grupo' => true,
        ])->assertCreated()
            ->assertJsonPath('data.lista_por_grupo', true);

        $this->assertTrue(ExamInvitation::query()->sole()->lista_por_grupo);
    }

    public function test_por_defecto_la_lista_va_junta(): void
    {
        $this->postJson($this->invitationsUrl($this->exam), ['id_docente' => $this->otroDocenteId])
            ->assertCreated()
            ->assertJsonPath('data.permite_agregar_grupos', true)
            ->assertJsonPath('data.lista_por_grupo', false);
    }

    public function test_la_base_no_admite_lista_separada_sin_permitir_grupos(): void
    {
        $this->expectException(QueryException::class);

        $this->createInvitation($this->exam, $this->otroDocenteId, ExamInvitation::PENDIENTE, [
            'permite_agregar_grupos' => false,
            'lista_por_grupo' => true,
        ]);
    }

    public function test_el_invitado_ve_la_opcion_en_el_detalle(): void
    {
        $invitation = $this->createInvitation($this->exam, $this->otroDocenteId, ExamInvitation::PENDIENTE, [
            'lista_por_grupo' => true,
        ]);
        $this->actAsTeacher($this->otroDocenteId);

        $this->getJson("/api/invitaciones/{$invitation->id_invitacion}")
            ->assertOk()
            ->assertJsonPath('data.lista_por_grupo', true);
    }

    public function test_durante_el_examen_la_lista_separada_trae_cada_grupo_con_su_docente(): void
    {
        $this->acceptWithGroup(true);
        $this->exam->update(['estado' => Exam::EN_CURSO]);

        $response = $this->getJson("/api/examenes/{$this->exam->id_examen}/en-curso")
            ->assertOk()
            ->assertJsonPath('data.lista_por_grupo', true)
            ->assertJsonPath('data.grupos', [
                ['id_grupo' => $this->grupoPropioId, 'num_grupo' => '1', 'docente' => 'Ana Rojas'],
                ['id_grupo' => $this->grupoAjenoId, 'num_grupo' => '3', 'docente' => 'Luis Vargas'],
            ])
            ->assertJsonPath('data.totales.total', 5);

        $byGroup = collect($response->json('data.estudiantes'))->countBy('id_grupo')->all();
        $this->assertSame([$this->grupoPropioId => 2, $this->grupoAjenoId => 3], $byGroup);
    }

    public function test_durante_el_examen_la_lista_junta_no_cambia(): void
    {
        $this->acceptWithGroup(false);
        $this->exam->update(['estado' => Exam::EN_CURSO]);

        $this->getJson("/api/examenes/{$this->exam->id_examen}/en-curso")
            ->assertOk()
            ->assertJsonPath('data.lista_por_grupo', false)
            ->assertJsonPath('data.totales.total', 5)
            ->assertJsonCount(5, 'data.estudiantes');
    }

    public function test_el_control_de_ingreso_cuenta_ingresos_por_grupo_y_muestra_el_docente(): void
    {
        $this->acceptWithGroup(true);
        app(RoomAssignmentService::class)->prepare($this->exam->id_examen);
        $url = "/api/control-ingreso/examenes/{$this->exam->id_examen}";

        $this->getJson("{$url}/estado")
            ->assertOk()
            ->assertJsonPath('data.total', 5)
            ->assertJsonPath('data.por_grupo', [
                ['id_grupo' => $this->grupoPropioId, 'num_grupo' => '1', 'docente' => 'Ana Rojas',
                    'total' => 2, 'ingresados' => 0],
                ['id_grupo' => $this->grupoAjenoId, 'num_grupo' => '3', 'docente' => 'Luis Vargas',
                    'total' => 3, 'ingresados' => 0],
            ]);

        $student = DB::table('grupo_estudiante as gs')
            ->join('estudiante as e', 'e.id_estudiante', '=', 'gs.id_estudiante')
            ->where('gs.id_grupo', $this->grupoAjenoId)
            ->orderBy('e.id_estudiante')
            ->first(['e.id_estudiante', 'e.cod_sis', 'e.ci']);

        $this->postJson("{$url}/verificar", [
            'cod_sis' => $student->cod_sis,
            'ci' => $student->ci,
            'id_ambiente' => $this->aulaId,
        ])->assertOk()
            ->assertJsonPath('data.grupo.num_grupo', '3')
            ->assertJsonPath('data.grupo.docente', 'Luis Vargas');

        $this->postJson("{$url}/confirmar-ingreso", [
            'id_estudiante' => $student->id_estudiante,
            'id_ambiente' => $this->aulaId,
        ])->assertCreated();

        $this->getJson("{$url}/estado")
            ->assertOk()
            ->assertJsonPath('data.por_grupo.0.ingresados', 0)
            ->assertJsonPath('data.por_grupo.1.ingresados', 1)
            ->assertJsonPath('data.ultimos_ingresos.0.id_grupo', $this->grupoAjenoId);
    }

    public function test_el_control_de_ingreso_con_lista_junta_no_trae_conteo_por_grupo(): void
    {
        $this->acceptWithGroup(false);
        app(RoomAssignmentService::class)->prepare($this->exam->id_examen);

        $status = $this->getJson("/api/control-ingreso/examenes/{$this->exam->id_examen}/estado")
            ->assertOk()
            ->assertJsonPath('data.total', 5)
            ->json('data');

        $this->assertArrayNotHasKey('por_grupo', $status);
    }
}
