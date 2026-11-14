<?php

namespace Tests\Feature\Exams;

use App\Models\AdmissionCall;
use App\Models\Exam;
use App\Models\ExamInvitation;
use App\Models\Role;
use App\Models\StudentExam;
use App\Services\EntryControl\RoomAssignmentService;
use App\Support\RecordStatus;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\Concerns\AssignsIncidentRole;
use Tests\Concerns\SeedsExamAssistants;
use Tests\TestCase;

class ExamAttendanceShowTest extends TestCase
{
    use AssignsIncidentRole;
    use DatabaseTransactions;
    use SeedsExamAssistants;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedExamAssistants();
    }

    public function test_el_detalle_usa_la_cuota_de_polling_y_respeta_su_limite(): void
    {
        config(['sciem.rate_limits.api' => 3, 'sciem.rate_limits.polling' => 1]);
        $exam = $this->createExam(['estado' => Exam::EN_CURSO]);
        $routes = Route::getRoutes();
        $router = $this->app['router'];
        $detail = $router->gatherRouteMiddleware($routes->getByName('examenes.estudiantes-ingresados.show'));
        $catalog = $router->gatherRouteMiddleware($routes->getByName('examenes.estudiantes-ingresados'));

        $this->assertContains(ThrottleRequests::class . ':polling', $detail);
        $this->assertNotContains(ThrottleRequests::class . ':api', $detail);
        $this->assertContains(ThrottleRequests::class . ':api', $catalog);
        $this->getJson($this->url($exam))->assertOk();
        $this->getJson($this->url($exam))->assertStatus(429)->assertHeader('Retry-After');
        $this->getJson('/api/examenes/estudiantes-ingresados')->assertOk();
    }

    public function test_rechaza_el_acceso_directo_de_un_docente_ajeno(): void
    {
        $exam = $this->createExam(['estado' => Exam::EN_CURSO]);
        $this->actAsTeacher($this->otroDocenteId);

        $this->getJson($this->url($exam))->assertForbidden()
            ->assertJson(fn (AssertableJson $json) => $json->missing('data')->etc());
    }

    public function test_rechaza_administradores_y_solicitudes_sin_autenticar(): void
    {
        $exam = $this->createExam(['estado' => Exam::EN_CURSO]);
        $this->assignRole($this->mariaId, Role::ADMINISTRADOR);
        $this->actAsUserId($this->mariaId);

        $this->getJson($this->url($exam))->assertForbidden();

        $this->actAsGuest();
        $this->getJson($this->url($exam))->assertUnauthorized();
    }

    public function test_deniega_a_un_auxiliar_sin_habilitacion_o_sin_ambiente_en_un_examen_multiaula(): void
    {
        $exam = $this->examWithAssistants(['estado' => Exam::EN_CURSO]);
        $unassignedExam = $this->createExam(['estado' => Exam::EN_CURSO], [$this->aulaId]);
        $this->assignRole($this->mariaId, Role::AUXILIAR);
        $this->actAsUserId($this->mariaId);

        $this->getJson($this->url($exam))->assertForbidden();
        $this->getJson($this->url($unassignedExam))->assertForbidden();
    }

    public function test_deniega_a_un_auxiliar_cuyo_rol_finalizo(): void
    {
        $exam = $this->examWithAssistants(['estado' => Exam::EN_CURSO], $this->aulaId);
        $this->assignRole($this->mariaId, Role::AUXILIAR);
        $this->actAsUserId($this->mariaId);
        DB::table('usuario_rol')->where('id_usuario', $this->mariaId)->update(['fecha_fin' => now()]);

        $this->getJson($this->url($exam))->assertForbidden();
    }

    public function test_rechaza_estados_fuera_del_catalogo_o_ingreso_sin_snapshot(): void
    {
        foreach ([Exam::PROGRAMADO, Exam::FINALIZADO, Exam::CANCELADO, Exam::EN_INGRESO] as $state) {
            $exam = $this->createExam(['estado' => $state]);

            $this->getJson($this->url($exam))->assertStatus(409)
                ->assertJson(fn (AssertableJson $json) => $json->missing('data')->etc());
        }
    }

    public function test_serializa_la_nomina_y_los_totales_del_examen_canonicamente_y_sin_escrituras(): void
    {
        $exam = $this->createExam(['estado' => Exam::EN_CURSO], [$this->aulaId]);
        $exam->groups()->attach([$this->grupoPropioId, $this->grupoPeriodoAnteriorId]);
        [$first, $second] = $this->studentIds();
        DB::table('grupo_estudiante')->where('id_grupo', $this->grupoPropioId)
            ->where('id_estudiante', $first)->update(['id_carrera' => $this->informaticaId]);
        DB::table('grupo_estudiante')->insert([
            'id_grupo' => $this->grupoPeriodoAnteriorId,
            'id_estudiante' => $first,
            'id_carrera' => $this->sistemasId,
            'fecha_inscripcion' => '2025-02-15',
            'estado' => RecordStatus::ACTIVE,
        ]);
        DB::table('estudiante')->where('id_estudiante', $second)->update(['ci' => null]);
        $this->recordEntry($exam, $first, StudentExam::INGRESO, $this->aulaId, '08:00');
        // La carrera sigue siendo la del grupo canónico aunque el ingreso indique otro grupo.
        DB::table('examen_estudiante')->where('id_examen', $exam->id_examen)->update([
            'id_grupo' => $this->grupoPeriodoAnteriorId,
        ]);
        $originalAttendance = DB::table('examen_estudiante')->where('id_examen', $exam->id_examen)->get();
        $originalExam = $exam->fresh()->getAttributes();
        $cache = Cache::store(config('sciem.entry_control_cache_store'));
        $presenceKey = "exam_{$exam->id_examen}_connected_users";
        $this->assertNull($cache->get($presenceKey));
        $students = DB::table('estudiante')->whereIn('id_estudiante', [$first, $second])
            ->orderBy('id_estudiante')->get();

        $this->getJson($this->url($exam))->assertOk()->assertExactJson(['data' => [
            'id_examen' => $exam->id_examen,
            'estado' => Exam::EN_CURSO,
            'id_ambiente_asignado' => null,
            'ambientes' => [['id_ambiente' => $this->aulaId, 'nro_aula' => '691A']],
            'estudiantes' => [
                [
                    'id_estudiante' => $first, 'cod_sis' => $students[0]->cod_sis, 'ci' => $students[0]->ci,
                    'nombre_completo' => 'Estudiante 1 Prueba',
                    'carrera' => ['id_carrera' => $this->informaticaId, 'nombre' => 'Ingenieria Informatica'],
                    'id_ambiente' => $this->aulaId, 'ambiente' => '691A', 'estado' => 'A_TIEMPO',
                    'hora_ingreso' => '08:00', 'expulsado_en' => null,
                ],
                [
                    'id_estudiante' => $second, 'cod_sis' => $students[1]->cod_sis, 'ci' => null,
                    'nombre_completo' => 'Estudiante 2 Prueba',
                    'carrera' => ['id_carrera' => $this->sistemasId, 'nombre' => 'Ingenieria de Sistemas'],
                    'id_ambiente' => $this->aulaId, 'ambiente' => '691A', 'estado' => 'AUSENTE',
                    'hora_ingreso' => null, 'expulsado_en' => null,
                ],
            ],
            'totales' => [
                'total' => 2, 'pendientes' => 0, 'ausentes' => 1, 'a_tiempo' => 1,
                'con_retraso' => 0, 'expulsados' => 0, 'no_habilitados' => 0, 'sin_clasificar' => 0,
            ],
        ]]);

        $this->assertSame($originalExam, $exam->fresh()->getAttributes());
        $this->assertEquals(
            $originalAttendance,
            DB::table('examen_estudiante')->where('id_examen', $exam->id_examen)->get()
        );
        $this->assertNull($cache->get($presenceKey));
    }

    public function test_distingue_pendientes_y_ausentes_al_cambiar_de_estado_sin_nomina_cargada(): void
    {
        $exam = $this->createExam([], [$this->aulaId]);
        $exam->groups()->attach($this->grupoPropioId);
        $this->travelTo(Carbon::parse($exam->fecha->toDateString() . ' 08:00', config('sciem.zona_horaria')));
        app(RoomAssignmentService::class)->prepare($exam->id_examen);

        $this->getJson($this->url($exam))->assertOk()
            ->assertJsonPath('data.estudiantes.0.estado', 'PENDIENTE')
            ->assertJsonPath('data.estudiantes.1.estado', 'PENDIENTE')
            ->assertJsonPath('data.totales.no_habilitados', 0)
            ->assertJsonPath('data.totales.pendientes', 2)
            ->assertJsonPath('data.totales.ausentes', 0);

        $this->travel(90)->minutes();
        $this->getJson($this->url($exam))->assertStatus(409);

        $exam->update(['estado' => Exam::EN_CURSO]);
        $this->getJson($this->url($exam))->assertOk()
            ->assertJsonPath('data.estudiantes.0.estado', 'AUSENTE')
            ->assertJsonPath('data.estudiantes.1.estado', 'AUSENTE')
            ->assertJsonPath('data.totales.ausentes', 2)
            ->assertJsonPath('data.totales.pendientes', 0);
    }

    public function test_una_marca_individual_no_acredita_que_se_cargo_la_nomina_de_habilitados(): void
    {
        $exam = $this->createExam(['estado' => Exam::EN_CURSO], [$this->aulaId]);
        $exam->groups()->attach($this->grupoPropioId);
        [$first] = $this->studentIds();
        $this->recordEntry($exam, $first, StudentExam::NO_INGRESO, null, null, 'NO_HABILITADO');

        $this->getJson($this->url($exam))->assertOk()
            ->assertJsonPath('data.estudiantes.0.estado', 'AUSENTE')
            ->assertJsonPath('data.totales.ausentes', 2)
            ->assertJsonPath('data.totales.no_habilitados', 0)
            ->assertJsonPath('data.totales.sin_clasificar', 0);
    }

    public function test_el_estado_de_ingreso_desconocido_cuenta_solo_como_sin_clasificar(): void
    {
        $exam = $this->createExam([], [$this->aulaId]);
        $exam->groups()->attach($this->grupoPropioId);
        [$first] = $this->studentIds();
        $this->travelTo(Carbon::parse($exam->fecha->toDateString() . ' 08:00', config('sciem.zona_horaria')));
        app(RoomAssignmentService::class)->prepare($exam->id_examen);
        $this->recordEntry($exam, $first, StudentExam::NO_INGRESO, null, null);

        // Simula un valor ajeno al contrato dentro de la transacción reversible de la prueba.
        DB::statement("ALTER TYPE estado_ingreso RENAME VALUE 'NO_INGRESO' TO 'ESTADO_LEGADO'");

        $this->getJson($this->url($exam))->assertOk()
            ->assertJsonPath('data.estudiantes.0.estado', 'SIN_CLASIFICAR')
            ->assertJsonPath('data.estudiantes.1.estado', 'PENDIENTE')
            ->assertJsonPath('data.totales.total', 2)
            ->assertJsonPath('data.totales.sin_clasificar', 1)
            ->assertJsonPath('data.totales.pendientes', 1)
            ->assertJsonPath('data.totales.ausentes', 0);

        $exam->update(['estado' => Exam::EN_CURSO]);
        $this->getJson($this->url($exam))->assertOk()
            ->assertJsonPath('data.estudiantes.0.estado', 'SIN_CLASIFICAR')
            ->assertJsonPath('data.estudiantes.1.estado', 'AUSENTE')
            ->assertJsonPath('data.totales.total', 2)
            ->assertJsonPath('data.totales.sin_clasificar', 1)
            ->assertJsonPath('data.totales.pendientes', 0)
            ->assertJsonPath('data.totales.ausentes', 1);
    }

    public function test_el_ci_se_entrega_a_cada_perfil_autorizado_y_puede_ser_nulo(): void
    {
        $exam = $this->examWithAssistants(['estado' => Exam::EN_CURSO], $this->aulaId);
        $exam->groups()->attach($this->grupoPropioId);
        $this->inviteCollaborator($exam, $this->docenteId, 'ACEPTADA', 1);
        [$first, $second] = $this->studentIds();
        DB::table('estudiante')->where('id_estudiante', $second)->update(['ci' => null]);
        $ci = DB::table('estudiante')->where('id_estudiante', $first)->value('ci');
        $this->assertNotNull($ci);

        foreach ([$this->docenteId, $this->otroDocenteId] as $teacherId) {
            $this->actAsTeacher($teacherId);
            $this->getJson($this->url($exam))->assertOk()
                ->assertJsonPath('data.estudiantes.0.ci', $ci)
                ->assertJson(fn (AssertableJson $json) => $json
                    ->has('data.estudiantes.1.ci')
                    ->where('data.estudiantes.1.ci', null)->etc());
        }

        $this->assignRole($this->mariaId, Role::AUXILIAR);
        $this->actAsUserId($this->mariaId);
        $this->getJson($this->url($exam))->assertOk()
            ->assertJsonCount(2, 'data.estudiantes')
            ->assertJsonPath('data.estudiantes.0.ci', $ci)
            ->assertJson(fn (AssertableJson $json) => $json
                ->has('data.estudiantes.1.ci')->where('data.estudiantes.1.ci', null)->etc());
    }

    public function test_auxiliar_solo_recibe_su_ambiente_con_ingresos_trasladados_y_totales_acotados(): void
    {
        $exam = $this->examWithAssistants(['estado' => Exam::EN_CURSO], $this->otraAulaId);
        $exam->groups()->attach($this->grupoPropioId);
        DB::table('ambiente')->whereIn('id_ambiente', [$this->aulaId, $this->otraAulaId])->update(['capacidad' => 1]);
        [$first, $second] = $this->studentIds();
        $this->assignRole($this->mariaId, Role::AUXILIAR);
        $this->actAsUserId($this->mariaId);

        $this->getJson($this->url($exam))->assertOk()
            ->assertJsonCount(1, 'data.estudiantes')
            ->assertJsonPath('data.estudiantes.0.id_estudiante', $second)
            ->assertJsonPath('data.totales.ausentes', 1);

        $this->recordEntry($exam, $first, StudentExam::CON_RETRASO, $this->otraAulaId, '08:20');
        $this->recordEntry($exam, $second, StudentExam::INGRESO, $this->aulaId, '08:05');

        $ciByStudent = DB::table('estudiante')->whereIn('id_estudiante', [$first, $second])->pluck('ci', 'id_estudiante');
        $query = http_build_query([
            'id_ambiente' => $this->aulaId,
            'ambiente' => $this->aulaId,
            'ci' => $ciByStudent[$second],
            'q' => $ciByStudent[$second],
        ]);
        $this->getJson($this->url($exam) . '?' . $query)->assertOk()
            ->assertJsonPath('data.id_ambiente_asignado', $this->otraAulaId)
            ->assertJsonPath('data.ambientes', [['id_ambiente' => $this->otraAulaId, 'nro_aula' => '692B']])
            ->assertJsonCount(1, 'data.estudiantes')
            ->assertJsonPath('data.estudiantes.0.id_estudiante', $first)
            ->assertJsonPath('data.estudiantes.0.ci', $ciByStudent[$first])
            ->assertJsonMissing(['ci' => $ciByStudent[$second]])
            ->assertJsonPath('data.estudiantes.0.estado', 'CON_RETRASO')
            ->assertJsonPath('data.totales.total', 1)
            ->assertJsonPath('data.totales.con_retraso', 1)
            ->assertJsonPath('data.totales.a_tiempo', 0);
    }

    public function test_expulsion_tiene_prioridad_y_la_nomina_conserva_a_quien_no_tiene_ambiente(): void
    {
        $exam = $this->createExam(['estado' => Exam::EN_CURSO], [$this->aulaId]);
        $exam->groups()->attach($this->grupoPropioId);
        DB::table('ambiente')->where('id_ambiente', $this->aulaId)->update(['capacidad' => 1]);
        [$first, $second] = $this->studentIds();
        $this->recordEntry($exam, $first, StudentExam::CON_RETRASO, $this->aulaId, '08:20');
        DB::table('examen_estudiante')->where('id_examen', $exam->id_examen)
            ->update(['expulsado_en' => '2026-11-14 12:30:00+00']);

        $this->getJson($this->url($exam))->assertOk()
            ->assertJsonPath('data.estudiantes.0.estado', 'EXPULSADO')
            ->assertJsonPath('data.estudiantes.0.expulsado_en', '2026-11-14T12:30:00+00:00')
            ->assertJsonPath('data.estudiantes.1.id_estudiante', $second)
            ->assertJsonPath('data.estudiantes.1.id_ambiente', null)
            ->assertJsonPath('data.totales.total', 2)
            ->assertJsonPath('data.totales.expulsados', 1)
            ->assertJsonPath('data.totales.con_retraso', 0);
    }

    public function test_no_infiere_carrera_si_la_inscripcion_del_grupo_multicarrera_no_la_indica(): void
    {
        $exam = $this->createExam(['estado' => Exam::EN_CURSO]);
        $exam->groups()->attach($this->grupoPropioId);
        $this->offerPair($this->informaticaId, $this->calculoId, $this->periodoActivoId);

        foreach ([$this->sistemasId, $this->informaticaId] as $careerId) {
            DB::table('grupo_oferta')->insertOrIgnore([
                'id_grupo' => $this->grupoPropioId,
                'id_carrera' => $careerId,
                'id_materia' => $this->calculoId,
                'id_gestion_periodo' => $this->periodoActivoId,
            ]);
        }

        [$first] = $this->studentIds();
        DB::table('grupo_estudiante')->where('id_grupo', $this->grupoPropioId)
            ->where('id_estudiante', $first)->update(['id_carrera' => $this->informaticaId]);

        $this->getJson($this->url($exam))->assertOk()
            ->assertJsonPath('data.estudiantes.0.carrera.id_carrera', $this->informaticaId)
            ->assertJsonPath('data.estudiantes.1.carrera', null)
            ->assertJsonPath('data.totales.total', 2);
    }

    public function test_colaborador_solo_accede_si_la_ultima_respuesta_del_propietario_es_aceptada(): void
    {
        $exam = $this->createExam(['estado' => Exam::EN_CURSO], [$this->aulaId, $this->otraAulaId]);
        $exam->groups()->attach($this->grupoPropioId);
        DB::table('ambiente')->whereIn('id_ambiente', [$this->aulaId, $this->otraAulaId])->update(['capacidad' => 1]);
        $this->actAsTeacher($this->otroDocenteId);

        $otherExam = $this->createExam(['estado' => Exam::EN_CURSO]);
        $this->inviteCollaborator($otherExam, $this->docenteId, 'ACEPTADA', 1);
        $this->inviteCollaborator($exam, $this->mariaId, 'ACEPTADA', 1);
        $this->inviteCollaborator($exam, $this->docenteId, 'PENDIENTE');
        $this->getJson($this->url($exam))->assertForbidden();

        $this->inviteCollaborator($exam, $this->docenteId, 'ACEPTADA', 2);
        $this->inviteCollaborator($exam, $this->docenteId, 'PENDIENTE');
        $this->getJson($this->url($exam))->assertOk()
            ->assertJsonCount(2, 'data.ambientes')->assertJsonCount(2, 'data.estudiantes');
        $catalog = $this->getJson('/api/examenes/estudiantes-ingresados')->assertOk()->json('data');
        $this->assertContains($exam->id_examen, array_column($catalog, 'id_examen'));

        $this->inviteCollaborator($exam, $this->docenteId, 'RECHAZADA', 3);
        $this->inviteCollaborator($exam, $this->docenteId, 'ACEPTADA', 2);
        $this->inviteCollaborator($exam, $this->mariaId, 'ACEPTADA', 4);
        $this->getJson($this->url($exam))->assertForbidden();
        $this->getJson('/api/examenes/estudiantes-ingresados')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_examen', $otherExam->id_examen);

        $this->inviteCollaborator($exam, $this->docenteId, 'ACEPTADA', 3);
        $this->getJson($this->url($exam))->assertOk();

        DB::table('usuario_rol')->where('id_usuario', $this->otroDocenteId)->update(['fecha_fin' => now()]);
        $this->getJson($this->url($exam))->assertForbidden();
    }

    public function test_invitado_con_rol_vigente_consulta_su_examen_y_el_examen_que_acepto(): void
    {
        $ownExam = $this->createExam(['estado' => Exam::EN_CURSO, 'id_usuario_docente' => $this->otroDocenteId]);
        $collaboration = $this->createExam(['estado' => Exam::EN_CURSO]);
        $this->inviteCollaborator($collaboration, $this->docenteId, 'ACEPTADA', 1);
        DB::table('usuario_rol')->where('id_usuario', $this->otroDocenteId)->update(['fecha_fin' => now()]);
        DB::table('usuario')->where('id_usuario', $this->otroDocenteId)->update(['cod_sis' => 'INV4821']);
        $this->assignRole($this->otroDocenteId, Role::INVITADO);
        $this->actAsUserId($this->otroDocenteId);

        $this->getJson($this->url($ownExam))->assertOk()->assertJsonPath('data.totales.total', 0);
        $this->getJson($this->url($collaboration))->assertOk();
    }

    public function test_excluye_el_detalle_de_un_examen_vinculado_a_una_convocatoria_de_postulantes(): void
    {
        $exam = $this->createExam([
            'estado' => Exam::EN_CURSO,
            'id_tipo_examen' => $this->examTypeId('ADMISION'),
            'id_carrera' => null,
            'id_materia' => null,
        ]);
        $call = AdmissionCall::create([
            'id_facultad' => $this->facultadId,
            'id_gestion_periodo' => $this->periodoActivoId,
            'nro_opciones' => '1',
            'estado' => RecordStatus::ACTIVE,
        ]);
        $exam->admissionExam()->create(['id_admision' => $call->id_admision, 'nro_opcion' => '1']);

        $this->getJson($this->url($exam))->assertForbidden();
    }

    private function studentIds(): array
    {
        return DB::table('grupo_estudiante')->where('id_grupo', $this->grupoPropioId)
            ->orderBy('id_estudiante')->pluck('id_estudiante')->map(static fn ($id): int => (int) $id)->all();
    }

    private function recordEntry(
        Exam $exam,
        int $studentId,
        string $state,
        ?int $roomId,
        ?string $enteredAt,
        string $eligibility = StudentExam::HABILITADO
    ): void {
        StudentExam::create([
            'id_examen' => $exam->id_examen,
            'id_estudiante' => $studentId,
            'id_grupo' => $this->grupoPropioId,
            'id_ambiente' => $roomId,
            'estado_habilitacion' => $eligibility,
            'estado_ingreso' => $state,
            'hora_ingreso' => $enteredAt,
        ]);
    }

    private function inviteCollaborator(Exam $exam, string $issuer, string $state, ?int $answerDay = null): void
    {
        ExamInvitation::create([
            'id_examen' => $exam->id_examen,
            'id_docente_creador' => $issuer,
            'id_docente_invitado' => $this->otroDocenteId,
            'fecha_invitacion' => '2026-11-01 10:00:00+00',
            'fecha_respuesta' => $answerDay === null ? null : sprintf('2026-11-%02d 12:00:00+00', $answerDay),
            'estado' => $state,
        ]);
    }

    private function url(Exam $exam): string
    {
        return "/api/examenes/{$exam->id_examen}/estudiantes-ingresados";
    }
}
