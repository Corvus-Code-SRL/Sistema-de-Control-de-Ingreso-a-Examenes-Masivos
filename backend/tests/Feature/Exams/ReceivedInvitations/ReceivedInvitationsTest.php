<?php

namespace Tests\Feature\Exams\ReceivedInvitations;

use App\Models\Exam;
use App\Models\ExamInvitation;
use App\Models\Role;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\AssignsIncidentRole;
use Tests\Concerns\SeedsCollaboratorInvitations;
use Tests\Concerns\SeedsExamCatalog;
use Tests\TestCase;

/** HU-027: bandeja de invitaciones recibidas y detalle de una invitación. */
class ReceivedInvitationsTest extends TestCase
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
        $this->actAsTeacher($this->otroDocenteId);
    }

    public function test_la_bandeja_separa_pendientes_aceptadas_y_cerradas_con_sus_totales(): void
    {
        $pending = $this->createInvitation($this->exam, $this->otroDocenteId);
        $accepted = $this->createInvitation(
            $this->createExam(['nombre_examen' => 'Aceptado', 'hora_inicio' => '10:00']),
            $this->otroDocenteId,
            ExamInvitation::ACEPTADA
        );
        $rejected = $this->createInvitation(
            $this->createExam(['nombre_examen' => 'Rechazado', 'hora_inicio' => '12:00']),
            $this->otroDocenteId,
            ExamInvitation::RECHAZADA
        );
        $void = $this->createInvitation(
            $this->createExam(['nombre_examen' => 'Sin efecto', 'hora_inicio' => '14:00']),
            $this->otroDocenteId,
            ExamInvitation::SIN_EFECTO
        );
        // Pendiente escrita, pero su examen ya abrió el control (sigue PROGRAMADO): se muestra sin efecto, en Cerradas.
        $stale = $this->createInvitation(
            $this->createExam([
                'nombre_examen' => 'Ya abrió',
                'hora_inicio' => '16:00',
                'ingreso_abierto_en' => '2026-11-01 15:50:00-04',
            ]),
            $this->otroDocenteId
        );

        $this->getJson('/api/invitaciones?estado=pendientes')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_invitacion', $pending->id_invitacion)
            ->assertJsonPath('data.0.estado', 'PENDIENTE')
            ->assertJsonPath('data.0.examen.nombre_examen', 'Parcial común')
            ->assertJsonPath('data.0.invitante.nombre_completo', 'Ana Rojas')
            ->assertJsonPath('meta.total_pendientes', 1)
            ->assertJsonPath('meta.total_aceptadas', 1)
            ->assertJsonPath('meta.total_cerradas', 3);

        $this->getJson('/api/invitaciones?estado=aceptadas')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_invitacion', $accepted->id_invitacion);

        $closed = $this->getJson('/api/invitaciones?estado=cerradas')->assertOk()->json('data');
        $this->assertEqualsCanonicalizing(
            [$rejected->id_invitacion, $void->id_invitacion, $stale->id_invitacion],
            array_column($closed, 'id_invitacion')
        );
        $states = array_column($closed, 'estado', 'id_invitacion');
        $this->assertSame('SIN_EFECTO', $states[$stale->id_invitacion]);
        $this->assertSame('RECHAZADA', $states[$rejected->id_invitacion]);
    }

    public function test_sin_filtro_muestra_las_pendientes_y_un_filtro_desconocido_responde_422(): void
    {
        $pending = $this->createInvitation($this->exam, $this->otroDocenteId);

        $this->getJson('/api/invitaciones')
            ->assertOk()
            ->assertJsonPath('data.0.id_invitacion', $pending->id_invitacion);

        $this->getJson('/api/invitaciones?estado=todas')
            ->assertStatus(422)
            ->assertJsonValidationErrors('estado');
    }

    public function test_no_muestra_invitaciones_dirigidas_a_otro_docente(): void
    {
        $thirdId = $this->createTeacherAccount('Carla', 'Mendez', '200100009', $this->calculoId);
        $this->createInvitation($this->exam, $thirdId);

        $this->getJson('/api/invitaciones?estado=pendientes')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total_pendientes', 0);
    }

    public function test_el_detalle_trae_ambientes_grupos_del_examen_y_grupos_propios(): void
    {
        $this->enrollStudents($this->grupoAjenoId, 3);
        // Un segundo grupo propio del invitado, sin nómina: no se puede sumar.
        $emptyGroupId = DB::table('grupo')->insertGetId([
            'id_carrera' => $this->sistemasId,
            'id_materia' => $this->calculoId,
            'num_grupo' => '9',
            'estado' => 'ACTIVO',
            'id_usuario_docente' => $this->otroDocenteId,
            'id_gestion_periodo' => $this->periodoActivoId,
        ], 'id_grupo');

        $invitation = $this->createInvitation($this->exam, $this->otroDocenteId);

        $response = $this->getJson("/api/invitaciones/{$invitation->id_invitacion}")
            ->assertOk()
            ->assertJsonPath('data.estado', 'PENDIENTE')
            ->assertJsonPath('data.permite_agregar_grupos', true)
            ->assertJsonPath('data.examen.periodo', '1/2026')
            ->assertJsonPath('data.ambientes.0.id_ambiente', $this->aulaId)
            ->assertJsonPath('data.grupos_examen.0.id_grupo', $this->grupoPropioId)
            ->assertJsonPath('data.grupos_examen.0.inscritos', 2);

        $mine = collect($response->json('data.mis_grupos'))->keyBy('id_grupo');
        $this->assertSame(
            ['id_grupo' => $this->grupoAjenoId, 'num_grupo' => '3', 'inscritos' => 3, 'seleccionable' => true,
                'motivo' => null, 'sumado' => false],
            $mine[$this->grupoAjenoId]
        );
        $this->assertFalse($mine[$emptyGroupId]['seleccionable']);
        $this->assertSame('sin nómina', $mine[$emptyGroupId]['motivo']);
    }

    public function test_el_detalle_de_una_invitacion_ajena_o_inexistente_responde_404(): void
    {
        $thirdId = $this->createTeacherAccount('Carla', 'Mendez', '200100009', $this->calculoId);
        $foreign = $this->createInvitation($this->exam, $thirdId);

        $this->getJson("/api/invitaciones/{$foreign->id_invitacion}")
            ->assertNotFound()
            ->assertJsonPath('message', 'No existe la invitación indicada.');
        $this->getJson('/api/invitaciones/999999')->assertNotFound();
        $this->getJson('/api/invitaciones/abc')->assertNotFound();
    }

    public function test_solo_un_docente_o_invitado_consulta_la_bandeja(): void
    {
        $assistantId = $this->createTeacherAccount('Raul', 'Soto', '200100010', null, ['rol' => Role::AUXILIAR]);
        $guestId = $this->createTeacherAccount('Irma', 'Paz', '200100011', null, ['rol' => Role::INVITADO]);

        $this->actAsUserId($assistantId);
        $this->getJson('/api/invitaciones')->assertForbidden();

        $this->actAsUserId($guestId);
        $this->getJson('/api/invitaciones')->assertOk();

        $this->actAsGuest();
        $this->getJson('/api/invitaciones')->assertUnauthorized();
    }
}
