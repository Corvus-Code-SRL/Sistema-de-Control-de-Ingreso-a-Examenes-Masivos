<?php

namespace Tests\Feature\Exams\CollaboratorInvitations;

use App\Models\Action;
use App\Models\AuditLog;
use App\Models\Exam;
use App\Models\ExamInvitation;
use App\Models\Role;
use App\Support\RecordStatus;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\AssignsIncidentRole;
use Tests\Concerns\SeedsCollaboratorInvitations;
use Tests\Concerns\SeedsExamCatalog;
use Tests\TestCase;

/** HU-026, criterios 6 a 11: enviar la invitación, sus rechazos y su traza. */
class SendInvitationTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsExamCatalog;
    use AssignsIncidentRole;
    use SeedsCollaboratorInvitations;

    private Exam $exam;

    private string $colleagueId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedExamCatalog();
        $this->exam = $this->createExam();
        $this->colleagueId = $this->otroDocenteId;
    }

    private function send(array $body = [], ?Exam $exam = null): \Illuminate\Testing\TestResponse
    {
        return $this->postJson(
            $this->invitationsUrl($exam ?? $this->exam),
            array_merge(['id_docente' => $this->colleagueId], $body)
        );
    }

    private function invitations(): \Illuminate\Database\Eloquent\Collection
    {
        return ExamInvitation::query()->orderBy('id_invitacion')->get();
    }

    public function test_crea_la_invitacion_pendiente_con_la_opcion_de_grupos_marcada_por_defecto(): void
    {
        $this->send()
            ->assertCreated()
            ->assertJsonPath('mensaje', 'Invitación enviada correctamente.')
            ->assertJsonPath('data.estado', 'PENDIENTE')
            ->assertJsonPath('data.permite_agregar_grupos', true)
            ->assertJsonPath('data.docente.id_usuario', $this->colleagueId)
            ->assertJsonPath('data.docente.nombre_completo', 'Luis Vargas')
            ->assertJsonPath('data.examen.id_examen', $this->exam->id_examen)
            ->assertJsonPath('data.fecha_respuesta', null);

        $invitation = $this->invitations()->sole();
        $this->assertSame(ExamInvitation::PENDIENTE, $invitation->estado);
        $this->assertSame($this->docenteId, $invitation->id_docente_creador);
        $this->assertSame($this->colleagueId, $invitation->id_docente_invitado);
        $this->assertTrue($invitation->permite_agregar_grupos);
        $this->assertNull($invitation->fecha_respuesta);
        $this->assertNull($invitation->fecha_sin_efecto);
        $this->assertNotNull($invitation->fecha_invitacion);
    }

    public function test_guarda_la_opcion_de_grupos_cuando_se_desmarca(): void
    {
        $this->send(['permite_agregar_grupos' => false])
            ->assertCreated()
            ->assertJsonPath('data.permite_agregar_grupos', false);

        $this->assertFalse($this->invitations()->sole()->permite_agregar_grupos);
    }

    public function test_registra_la_invitacion_en_la_bitacora_con_usuario_y_fecha(): void
    {
        $this->send(['permite_agregar_grupos' => false])->assertCreated();

        $invitation = $this->invitations()->sole();
        $log = AuditLog::query()->where('tabla_afectada', 'invitacion_examen')->sole();

        $this->assertSame('INVITAR_COLABORADOR', Action::query()->whereKey($log->id_accion)->value('operacion'));
        $this->assertSame($this->docenteId, $log->id_usuario);
        $this->assertTrue($log->fecha_hora->diffInSeconds(now()) < 60);
        $this->assertNull($log->antiguo_valor);
        $this->assertSame($invitation->id_invitacion, $log->nuevo_valor['id_invitacion']);
        $this->assertSame($this->exam->id_examen, $log->nuevo_valor['id_examen']);
        $this->assertSame($this->colleagueId, $log->nuevo_valor['id_docente_invitado']);
        $this->assertSame('PENDIENTE', $log->nuevo_valor['estado']);
        $this->assertFalse($log->nuevo_valor['permite_agregar_grupos']);
    }

    public function test_una_invitacion_pendiente_o_aceptada_impide_invitar_de_nuevo(): void
    {
        $this->send()->assertCreated();

        $this->send()
            ->assertStatus(409)
            ->assertJsonPath('message', 'El docente ya tiene una invitación pendiente o aceptada para este examen.');

        $this->invitations()->sole()->update([
            'estado' => ExamInvitation::ACEPTADA,
            'fecha_respuesta' => now(),
        ]);

        $this->send()->assertStatus(409);
        $this->assertCount(1, $this->invitations());
        $this->assertSame(1, AuditLog::query()->where('tabla_afectada', 'invitacion_examen')->count());
    }

    /** @dataProvider closedStates */
    public function test_tras_rechazada_o_sin_efecto_se_puede_invitar_de_nuevo(string $state): void
    {
        $this->createInvitation($this->exam, $this->colleagueId, $state);

        $this->send()->assertCreated()->assertJsonPath('data.estado', 'PENDIENTE');

        $this->assertSame(
            [$state, ExamInvitation::PENDIENTE],
            $this->invitations()->pluck('estado')->all()
        );
    }

    public static function closedStates(): array
    {
        return [
            'rechazada' => [ExamInvitation::RECHAZADA],
            'sin efecto' => [ExamInvitation::SIN_EFECTO],
        ];
    }

    public function test_la_invitacion_a_otro_examen_no_cuenta_como_repetida(): void
    {
        $this->createInvitation($this->createExam(['nombre_examen' => 'Otro']), $this->colleagueId);

        $this->send()->assertCreated();
    }

    public function test_un_docente_que_no_creo_el_examen_no_puede_invitar_a_el(): void
    {
        $outsider = $this->createTeacherAccount('Raúl', 'Tapia', '200300001', $this->calculoId);
        $this->actAsTeacher($outsider);

        $this->send(['id_docente' => $this->docenteId])
            ->assertForbidden()
            ->assertJsonPath('message', 'Solo el docente que creó el examen puede invitar colaboradores a él.');

        $this->assertCount(0, $this->invitations());
    }

    public function test_un_colaborador_que_acepto_no_puede_invitar_a_un_examen_ajeno(): void
    {
        $this->createInvitation($this->exam, $this->colleagueId, ExamInvitation::ACEPTADA);
        $third = $this->createTeacherAccount('Sara', 'Gutiérrez', '200300002', $this->calculoId);
        $this->actAsTeacher($this->colleagueId);

        $this->send(['id_docente' => $third])->assertForbidden();
    }

    public function test_un_examen_que_ya_abrio_el_ingreso_responde_409(): void
    {
        $this->exam->forceFill($this->examStateAttributes('INGRESO_ABIERTO'))->save();

        $this->send()
            ->assertStatus(409)
            ->assertJsonPath(
                'message',
                'El control de ingreso del examen ya se inició: ya no se puede invitar docentes.'
            );

        $this->assertCount(0, $this->invitations());
    }

    public function test_un_examen_cancelado_responde_409(): void
    {
        $this->exam->update(['estado' => Exam::CANCELADO]);

        $this->send()
            ->assertStatus(409)
            ->assertJsonPath('message', 'El examen está cancelado: ya no se puede invitar docentes.');
    }

    public function test_un_examen_inexistente_o_con_id_fuera_de_rango_responde_404(): void
    {
        $this->postJson('/api/docente/invitaciones/examenes/987654', ['id_docente' => $this->colleagueId])
            ->assertNotFound();
        $this->postJson('/api/docente/invitaciones/examenes/9999999999', ['id_docente' => $this->colleagueId])
            ->assertNotFound();
        $this->postJson('/api/docente/invitaciones/examenes/abc', ['id_docente' => $this->colleagueId])
            ->assertNotFound();
    }

    public function test_un_docente_inexistente_responde_404(): void
    {
        $this->send(['id_docente' => (string) Str::uuid()])->assertNotFound();
    }

    public function test_el_docente_es_obligatorio_y_debe_ser_un_uuid(): void
    {
        $this->postJson($this->invitationsUrl($this->exam), [])
            ->assertStatus(422)->assertJsonValidationErrors('id_docente');
        $this->send(['id_docente' => 'no-es-uuid'])
            ->assertStatus(422)->assertJsonValidationErrors('id_docente');
        $this->send(['permite_agregar_grupos' => 'quizá'])
            ->assertStatus(422)->assertJsonValidationErrors('permite_agregar_grupos');
    }

    public function test_no_puede_invitarse_a_si_mismo(): void
    {
        $this->send(['id_docente' => $this->docenteId])
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_docente');

        $this->assertCount(0, $this->invitations());
    }

    public function test_rechaza_a_un_docente_sin_grupos_de_la_materia_en_el_periodo_vigente(): void
    {
        $otherSubject = $this->createTeacherAccount('Tito', 'Rivas', '200300003', $this->basesDatosId);
        $previousPeriod = $this->createTeacherAccount('Ugo', 'Rivas', '200300004', $this->calculoId, [
            'periodo' => $this->periodoAnteriorId,
        ]);

        foreach ([$otherSubject, $previousPeriod] as $teacher) {
            $this->send(['id_docente' => $teacher])
                ->assertStatus(422)
                ->assertJsonPath(
                    'errors.id_docente.0',
                    'El docente no dicta grupos de la materia del examen en el período vigente.'
                );
        }

        $this->assertCount(0, $this->invitations());
    }

    public function test_rechaza_cuentas_inactivas_y_roles_que_no_ejercen_la_docencia(): void
    {
        $inactive = $this->createTeacherAccount('Vera', 'Soto', '200300005', $this->calculoId, [
            'estado' => RecordStatus::INACTIVE,
        ]);
        $assistant = $this->createTeacherAccount('Wally', 'Soto', '200300006', $this->calculoId, [
            'rol' => Role::AUXILIAR,
        ]);

        $this->send(['id_docente' => $inactive])->assertStatus(422);
        $this->send(['id_docente' => $assistant])->assertStatus(422);
    }

    public function test_admite_invitar_a_un_docente_con_rol_invitado(): void
    {
        $guest = $this->createTeacherAccount('Xavi', 'Mora', 'INV4821', $this->calculoId, ['rol' => Role::INVITADO]);

        $this->send(['id_docente' => $guest])->assertCreated();
    }

    public function test_bloquea_la_fila_del_examen_al_enviar(): void
    {
        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = strtolower($query->sql);
        });

        $this->send()->assertCreated();

        $locked = collect($queries)->contains(
            fn (string $sql) => str_contains($sql, 'from "examen"') && str_contains($sql, 'for update')
        );
        $this->assertTrue($locked, 'send() debe bloquear la fila del examen con FOR UPDATE.');
    }

    public function test_el_indice_unico_parcial_rechaza_dos_invitaciones_vigentes(): void
    {
        $this->createInvitation($this->exam, $this->colleagueId);

        foreach ([ExamInvitation::PENDIENTE, ExamInvitation::ACEPTADA] as $state) {
            try {
                DB::transaction(fn () => $this->createInvitation($this->exam, $this->colleagueId, $state));
                $this->fail('El índice uq_invitacion_examen_vigente debía rechazar la segunda invitación.');
            } catch (QueryException $exception) {
                $this->assertSame('23505', $exception->getCode());
            }
        }
    }

    public function test_si_una_invitacion_concurrente_gana_la_carrera_responde_409(): void
    {
        $competing = function () {
            DB::table('invitacion_examen')->insert([
                'id_examen' => $this->exam->id_examen,
                'id_docente_creador' => $this->docenteId,
                'id_docente_invitado' => $this->colleagueId,
                'fecha_invitacion' => now(),
                'estado' => ExamInvitation::PENDIENTE,
            ]);
        };

        // La invitación rival se cuela entre la comprobación y el INSERT: solo el índice puede atajarla.
        ExamInvitation::creating($competing);

        try {
            $this->send()
                ->assertStatus(409)
                ->assertJsonPath('message', 'El docente ya tiene una invitación pendiente o aceptada para este examen.');
        } finally {
            ExamInvitation::flushEventListeners();
        }

        $this->assertSame(0, AuditLog::query()->where('tabla_afectada', 'invitacion_examen')->count());
    }
}
