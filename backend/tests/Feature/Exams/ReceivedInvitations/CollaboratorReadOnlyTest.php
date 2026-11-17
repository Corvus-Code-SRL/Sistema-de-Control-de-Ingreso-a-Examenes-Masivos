<?php

namespace Tests\Feature\Exams\ReceivedInvitations;

use App\Models\Exam;
use App\Models\ExamInvitation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\AssignsIncidentRole;
use Tests\Concerns\SeedsCollaboratorInvitations;
use Tests\Concerns\SeedsExamCatalog;
use Tests\TestCase;

/**
 * HU-027: el creador administra el examen y el colaborador lo controla. El colaborador ve el examen en
 * Programados y en su detalle, solo para leerlo, y recibe `es_creador: false` para que la interfaz oculte
 * lo que el servidor le niega (cerrar el ingreso ordinario, finalizar, modificar, cancelar, grupos).
 */
class CollaboratorReadOnlyTest extends TestCase
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
        $this->exam = $this->createExam(['nombre_examen' => 'Parcial común'], [$this->aulaId]);
        $this->exam->groups()->attach($this->grupoPropioId);
    }

    private function accept(): void
    {
        $this->createInvitation($this->exam, $this->otroDocenteId, ExamInvitation::ACEPTADA);
    }

    public function test_el_colaborador_ve_el_examen_en_programados_con_es_creador_falso(): void
    {
        $this->actAsTeacher($this->otroDocenteId);
        $this->getJson('/api/examenes?vista=programados')->assertOk()->assertJsonCount(0, 'data');

        $this->accept();

        $this->getJson('/api/examenes?vista=programados')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_examen', $this->exam->id_examen)
            ->assertJsonPath('data.0.es_creador', false);
        $this->getJson('/api/examenes')
            ->assertOk()
            ->assertJsonPath('data.0.id_examen', $this->exam->id_examen)
            ->assertJsonPath('data.0.es_creador', false);
    }

    public function test_una_invitacion_que_no_esta_aceptada_no_muestra_el_examen(): void
    {
        $this->actAsTeacher($this->otroDocenteId);

        foreach ([ExamInvitation::PENDIENTE, ExamInvitation::RECHAZADA, ExamInvitation::SIN_EFECTO] as $state) {
            $invitation = $this->createInvitation($this->exam, $this->otroDocenteId, $state);

            $this->getJson('/api/examenes?vista=programados')->assertOk()->assertJsonCount(0, 'data');
            $this->getJson("/api/examenes/{$this->exam->id_examen}")->assertForbidden();

            $invitation->delete();
        }
    }

    public function test_la_vista_del_creador_no_cambia_y_marca_es_creador(): void
    {
        $this->accept();
        $other = $this->createExam(['nombre_examen' => 'Del colaborador', 'id_usuario_docente' => $this->otroDocenteId]);

        $this->getJson('/api/examenes?vista=programados')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_examen', $this->exam->id_examen)
            ->assertJsonPath('data.0.es_creador', true);

        $this->actAsTeacher($this->otroDocenteId);
        $ids = collect($this->getJson('/api/examenes?vista=programados')->assertOk()->json('data'))
            ->pluck('es_creador', 'id_examen')->all();

        $this->assertEquals([$this->exam->id_examen => false, $other->id_examen => true], $ids);
    }

    public function test_el_examen_donde_solo_colabora_no_es_invitable_ni_editable(): void
    {
        $this->accept();
        $this->actAsTeacher($this->otroDocenteId);

        $this->getJson('/api/docente/invitaciones/examenes')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_el_colaborador_ve_el_detalle_pero_no_edita_cancela_ni_asigna_grupos(): void
    {
        $this->accept();
        $this->actAsTeacher($this->otroDocenteId);
        $url = "/api/examenes/{$this->exam->id_examen}";

        $this->getJson($url)
            ->assertOk()
            ->assertJsonPath('data.id_examen', $this->exam->id_examen)
            ->assertJsonPath('data.es_creador', false);

        $this->putJson($url, $this->validPayload(['nombre_examen' => 'Cambiado']))->assertForbidden();
        $this->postJson("{$url}/cancelar")->assertForbidden();
        $this->postJson("{$url}/grupos", ['grupos' => [$this->grupoPropioId]])->assertForbidden();

        $this->assertDatabaseHas('examen', [
            'id_examen' => $this->exam->id_examen,
            'nombre_examen' => 'Parcial común',
            'estado' => Exam::PROGRAMADO,
        ]);
    }

    public function test_el_creador_ve_su_detalle_con_es_creador_verdadero(): void
    {
        $this->accept();

        $this->getJson("/api/examenes/{$this->exam->id_examen}")
            ->assertOk()
            ->assertJsonPath('data.es_creador', true);
    }

    public function test_el_control_de_ingreso_informa_si_es_creador(): void
    {
        $this->exam->forceFill(['ingreso_abierto_en' => now()])->save();
        $this->accept();

        $this->getJson('/api/control-ingreso/examenes')
            ->assertOk()
            ->assertJsonPath('data.0.rol_controlador', 'DOCENTE')
            ->assertJsonPath('data.0.es_creador', true);

        $this->actAsTeacher($this->otroDocenteId);

        $this->getJson('/api/control-ingreso/examenes')
            ->assertOk()
            ->assertJsonPath('data.0.rol_controlador', 'DOCENTE')
            ->assertJsonPath('data.0.es_creador', false);
        $this->getJson("/api/control-ingreso/examenes/{$this->exam->id_examen}/contexto")
            ->assertOk()
            ->assertJsonPath('data.rol_controlador', 'DOCENTE')
            ->assertJsonPath('data.es_creador', false);
    }

    public function test_el_colaborador_no_cierra_el_ingreso_ordinario(): void
    {
        $this->exam->forceFill($this->entryOpen())->save();
        $this->accept();
        $this->actAsTeacher($this->otroDocenteId);

        $this->postJson("/api/control-ingreso/examenes/{$this->exam->id_examen}/cerrar-ingreso-ordinario")
            ->assertForbidden();

        $this->assertNull($this->exam->fresh()->ingreso_ordinario_cerrado_en);
    }

    public function test_el_examen_en_curso_informa_es_creador_y_el_colaborador_no_lo_finaliza(): void
    {
        $this->exam->update(['estado' => Exam::EN_CURSO]);
        $this->accept();

        $this->getJson("/api/examenes/{$this->exam->id_examen}/en-curso")
            ->assertOk()
            ->assertJsonPath('data.es_creador', true);

        $this->actAsTeacher($this->otroDocenteId);

        $this->getJson("/api/examenes/{$this->exam->id_examen}/en-curso")
            ->assertOk()
            ->assertJsonPath('data.rol_controlador', 'DOCENTE')
            ->assertJsonPath('data.es_creador', false);
        $this->postJson("/api/examenes/{$this->exam->id_examen}/finalizar")->assertForbidden();

        $this->assertSame(Exam::EN_CURSO, $this->exam->fresh()->estado);
    }
}
