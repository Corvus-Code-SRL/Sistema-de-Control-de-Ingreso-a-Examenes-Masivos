<?php

namespace Tests\Feature\Exams\ReceivedInvitations;

use App\Models\Action;
use App\Models\AuditLog;
use App\Models\Exam;
use App\Models\ExamInvitation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\AssignsIncidentRole;
use Tests\Concerns\SeedsCollaboratorInvitations;
use Tests\Concerns\SeedsExamCatalog;
use Tests\TestCase;

/** HU-027: aceptar (sumando grupos) o rechazar una invitación recibida. */
class RespondInvitationTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsExamCatalog;
    use AssignsIncidentRole;
    use SeedsCollaboratorInvitations;

    private Exam $exam;

    private ExamInvitation $invitation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedExamCatalog();
        $this->exam = $this->createExam([], [$this->aulaId]);
        $this->exam->groups()->attach($this->grupoPropioId);
        $this->enrollStudents($this->grupoAjenoId, 3);
        $this->invitation = $this->createInvitation($this->exam, $this->otroDocenteId);
        $this->actAsTeacher($this->otroDocenteId);
    }

    private function accept(array $groups = [], ?ExamInvitation $invitation = null): TestResponse
    {
        $id = ($invitation ?? $this->invitation)->id_invitacion;

        return $this->postJson("/api/invitaciones/{$id}/aceptar", ['grupos' => $groups]);
    }

    private function reject(?ExamInvitation $invitation = null): TestResponse
    {
        $id = ($invitation ?? $this->invitation)->id_invitacion;

        return $this->postJson("/api/invitaciones/{$id}/rechazar");
    }

    private function examGroupIds(): array
    {
        return DB::table('grupo_examen')->where('id_examen', $this->exam->id_examen)
            ->orderBy('id_grupo')->pluck('id_grupo')->map(fn ($id) => (int) $id)->all();
    }

    private function logsOf(string $operation)
    {
        $actionId = Action::query()->where('operacion', $operation)->value('id_accion');

        return AuditLog::query()->where('id_accion', $actionId)->get();
    }

    public function test_aceptar_suma_los_grupos_elegidos_y_registra_la_respuesta(): void
    {
        $this->accept([$this->grupoAjenoId])
            ->assertOk()
            ->assertJsonPath('mensaje', 'Invitación aceptada.')
            ->assertJsonPath('data.estado', 'ACEPTADA')
            ->assertJsonPath('data.mis_grupos.0.sumado', true);

        $invitation = $this->invitation->fresh();
        $this->assertSame(ExamInvitation::ACEPTADA, $invitation->estado);
        $this->assertNotNull($invitation->fecha_respuesta);
        $this->assertNull($invitation->fecha_sin_efecto);
        $this->assertSame([$this->grupoPropioId, $this->grupoAjenoId], $this->examGroupIds());

        $log = $this->logsOf('ACEPTAR_INVITACION')->sole();
        $this->assertSame($this->otroDocenteId, $log->id_usuario);
        $this->assertSame('invitacion_examen', $log->tabla_afectada);
        $this->assertSame('PENDIENTE', $log->antiguo_valor['estado']);
        $this->assertSame('ACEPTADA', $log->nuevo_valor['estado']);
        $this->assertSame([$this->grupoAjenoId], $log->nuevo_valor['grupos_sumados']);
    }

    public function test_aceptar_sin_grupos_deja_el_examen_como_estaba(): void
    {
        $this->accept()->assertOk()->assertJsonPath('data.estado', 'ACEPTADA');

        $this->assertSame([$this->grupoPropioId], $this->examGroupIds());
    }

    public function test_si_la_invitacion_no_permite_grupos_solo_se_acepta_sin_ellos(): void
    {
        $this->invitation->update(['permite_agregar_grupos' => false]);

        $this->accept([$this->grupoAjenoId])
            ->assertStatus(422)
            ->assertJsonPath('errors.grupos.0', 'Esta invitación no permite agregar grupos al examen.');
        $this->assertSame(ExamInvitation::PENDIENTE, $this->invitation->fresh()->estado);

        $this->accept()->assertOk()->assertJsonPath('data.permite_agregar_grupos', false);
        $this->assertSame([$this->grupoPropioId], $this->examGroupIds());
    }

    public function test_rechaza_grupos_ajenos_o_sin_nomina_sin_aceptar(): void
    {
        $this->accept([$this->grupoPropioId])->assertStatus(422)->assertJsonValidationErrors('grupos');

        $emptyGroupId = DB::table('grupo')->insertGetId([
            'id_carrera' => $this->sistemasId,
            'id_materia' => $this->calculoId,
            'num_grupo' => '9',
            'estado' => 'ACTIVO',
            'id_usuario_docente' => $this->otroDocenteId,
            'id_gestion_periodo' => $this->periodoActivoId,
        ], 'id_grupo');

        $this->accept([$emptyGroupId])
            ->assertStatus(422)
            ->assertJsonPath('errors.grupos.0', 'El grupo 9 no tiene nómina cargada.');

        $this->assertSame(ExamInvitation::PENDIENTE, $this->invitation->fresh()->estado);
        $this->assertSame([$this->grupoPropioId], $this->examGroupIds());
        $this->assertCount(0, $this->logsOf('ACEPTAR_INVITACION'));
    }

    public function test_rechaza_un_grupo_que_repite_estudiantes_del_examen(): void
    {
        $shared = DB::table('grupo_estudiante')->where('id_grupo', $this->grupoPropioId)->value('id_estudiante');
        DB::table('grupo_estudiante')->insert([
            'id_grupo' => $this->grupoAjenoId,
            'id_estudiante' => $shared,
            'fecha_inscripcion' => '2026-02-15',
            'estado' => 'ACTIVO',
        ]);

        $this->accept([$this->grupoAjenoId])
            ->assertStatus(422)
            ->assertJsonPath('errors.grupos.0', 'Un estudiante figura en más de uno de los grupos seleccionados.');
    }

    public function test_valida_la_forma_de_los_grupos(): void
    {
        $this->postJson("/api/invitaciones/{$this->invitation->id_invitacion}/aceptar", ['grupos' => 'x'])
            ->assertStatus(422)->assertJsonValidationErrors('grupos');
        $this->accept(['abc'])->assertStatus(422)->assertJsonValidationErrors('grupos.0');
    }

    public function test_rechazar_cierra_la_invitacion_y_registra_la_respuesta(): void
    {
        $this->reject()
            ->assertOk()
            ->assertJsonPath('mensaje', 'Invitación rechazada.')
            ->assertJsonPath('data.estado', 'RECHAZADA');

        $invitation = $this->invitation->fresh();
        $this->assertSame(ExamInvitation::RECHAZADA, $invitation->estado);
        $this->assertNotNull($invitation->fecha_respuesta);
        $this->assertSame([$this->grupoPropioId], $this->examGroupIds());

        $log = $this->logsOf('RECHAZAR_INVITACION')->sole();
        $this->assertSame($this->otroDocenteId, $log->id_usuario);
        $this->assertSame('RECHAZADA', $log->nuevo_valor['estado']);
    }

    /** @dataProvider answeredStates */
    public function test_una_invitacion_ya_respondida_o_sin_efecto_responde_409(string $state): void
    {
        $this->invitation->delete();
        $answered = $this->createInvitation($this->exam, $this->otroDocenteId, $state);

        $this->accept([], $answered)
            ->assertStatus(409)
            ->assertJsonPath('message', 'La invitación ya fue respondida o quedó sin efecto.');
        $this->reject($answered)->assertStatus(409);
        $this->assertSame($state, $answered->fresh()->estado);
    }

    public static function answeredStates(): array
    {
        return [
            'aceptada' => [ExamInvitation::ACEPTADA],
            'rechazada' => [ExamInvitation::RECHAZADA],
            'sin efecto' => [ExamInvitation::SIN_EFECTO],
        ];
    }

    /** @dataProvider closedExams */
    public function test_si_el_examen_ya_abrio_el_control_o_no_esta_programado_responde_409(array $examChanges): void
    {
        $this->exam->forceFill($examChanges)->save();

        $this->accept([$this->grupoAjenoId])
            ->assertStatus(409)
            ->assertJsonPath(
                'message',
                'La invitación ya no se puede responder: el examen ya abrió el control de ingreso o no está programado.'
            );
        $this->reject()->assertStatus(409);

        $this->assertSame(ExamInvitation::PENDIENTE, $this->invitation->fresh()->estado);
        $this->assertSame([$this->grupoPropioId], $this->examGroupIds());
    }

    public static function closedExams(): array
    {
        // HU-038: con el control de ingreso abierto el examen sigue PROGRAMADO; lo marca ingreso_abierto_en.
        return [
            'control de ingreso abierto' => [['ingreso_abierto_en' => '2026-11-01 07:50:00-04']],
            'en curso' => [['estado' => Exam::EN_CURSO, 'ingreso_abierto_en' => '2026-11-01 07:50:00-04']],
            'cancelado' => [['estado' => Exam::CANCELADO]],
        ];
    }

    public function test_no_se_responde_la_invitacion_de_otro_docente(): void
    {
        $thirdId = $this->createTeacherAccount('Carla', 'Mendez', '200100009', $this->calculoId);
        $foreign = $this->createInvitation($this->exam, $thirdId);

        $this->accept([], $foreign)->assertNotFound();
        $this->reject($foreign)->assertNotFound();
        $this->assertSame(ExamInvitation::PENDIENTE, $foreign->fresh()->estado);
    }
}
