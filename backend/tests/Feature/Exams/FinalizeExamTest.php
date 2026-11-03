<?php

namespace Tests\Feature\Exams;

use App\Jobs\FinalizeExpiredExamsJob;
use App\Models\Action;
use App\Models\AuditLog;
use App\Models\Exam;
use App\Models\Role;
use App\Services\EntryControl\EntryControlSnapshotService;
use App\Services\EntryControl\RoomAssignmentService;
use App\Services\Exams\ExamHistoryService;
use App\Services\Exams\ExamLifecycleService;
use App\Services\Exams\ExamParticipantService;
use App\Services\Exams\ExamTimingService;
use App\Services\Exams\FinalReportService;
use App\Services\Security\AuditLogService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsExamCatalog;
use Tests\TestCase;

class FinalizeExamTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsExamCatalog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedExamCatalog();
    }

    private function url(Exam $exam): string
    {
        return "/api/examenes/{$exam->id_examen}/finalizar";
    }

    public function test_finalizacion_conserva_estudiantes_esperados_aunque_cambie_la_nomina(): void
    {
        $exam = $this->createExam(['estado' => Exam::EN_CURSO], [$this->aulaId]);
        DB::table('grupo_examen')->insert([
            'id_examen' => $exam->id_examen,
            'id_grupo' => $this->grupoPropioId,
        ]);

        $this->postJson($this->url($exam))
            ->assertOk()
            ->assertJsonPath('data.estado', Exam::FINALIZADO);

        $this->assertSame(2, DB::table('examen_estudiante')
            ->where('id_examen', $exam->id_examen)
            ->where('estado_ingreso', 'NO_INGRESO')
            ->where('estado_habilitacion', 'HABILITADO')
            ->count());

        $this->enrollStudents($this->grupoPropioId, 5);

        $this->assertSame(7, DB::table('grupo_estudiante')
            ->where('id_grupo', $this->grupoPropioId)
            ->count());

        $counts = app(ExamParticipantService::class)->counts((int) $exam->id_examen);
        $this->assertSame(2, $counts['esperados']);
        $this->assertSame(0, $counts['ingresados']);
        $this->assertSame(2, $counts['pendientes']);

        $this->getJson("/api/examenes/{$exam->id_examen}")
            ->assertOk()
            ->assertJsonPath('data.grupos.0.cantidad_estudiantes', 2);
    }

    public function test_docente_creador_finaliza_en_curso_desactiva_snapshot_y_audita(): void
    {
        $exam = $this->createExam(['estado' => Exam::EN_INGRESO], [$this->aulaId]);
        DB::table('grupo_examen')->insert([
            'id_examen' => $exam->id_examen,
            'id_grupo' => $this->grupoPropioId,
        ]);
        app(RoomAssignmentService::class)->prepare((int) $exam->id_examen);
        $this->assertTrue(app(EntryControlSnapshotService::class)->ready((int) $exam->id_examen));
        $exam->update(['estado' => Exam::EN_CURSO]);

        $finishedAt = Carbon::parse('2026-10-01T15:32:45Z');
        Carbon::setTestNow($finishedAt);
        try {
            $this->postJson($this->url($exam))
                ->assertOk()
                ->assertJsonPath('mensaje', 'Examen finalizado correctamente.')
                ->assertJsonPath('data.estado', Exam::FINALIZADO)
                ->assertJsonPath('data.fin_real_en', $finishedAt->toIso8601String())
                ->assertJsonPath('data.fin_automatico', false);

            $this->postJson($this->url($exam))->assertStatus(409);
        } finally {
            Carbon::setTestNow();
        }

        $closed = $exam->fresh();
        $this->assertSame(Exam::FINALIZADO, $closed->estado);
        $this->assertTrue($closed->fin_real_en->equalTo($finishedAt));
        $this->assertFalse($closed->fin_automatico);
        $this->assertFalse(app(EntryControlSnapshotService::class)->ready((int) $exam->id_examen));

        $log = AuditLog::query()->where('tabla_afectada', 'examen')->sole();
        $this->assertSame(
            'FINALIZAR',
            Action::query()->whereKey($log->id_accion)->value('operacion')
        );
        $this->assertSame($this->docenteId, $log->id_usuario);
        $this->assertEquals(
            ['id_examen' => $exam->id_examen, 'estado' => Exam::EN_CURSO],
            $log->antiguo_valor
        );
        $this->assertEquals(
            ['id_examen' => $exam->id_examen, 'estado' => Exam::FINALIZADO],
            $log->nuevo_valor
        );
        $this->assertSame(2, DB::table('examen_estudiante')
            ->where('id_examen', $exam->id_examen)
            ->where('estado_ingreso', 'NO_INGRESO')
            ->count());
    }

    public function test_solo_docente_creador_puede_finalizar(): void
    {
        $exam = $this->createExam([
            'estado' => Exam::EN_CURSO,
            'id_usuario_docente' => $this->otroDocenteId,
        ]);

        $this->postJson($this->url($exam))->assertForbidden();

        $this->assertSame(Exam::EN_CURSO, $exam->fresh()->estado);
        $this->assertSame(0, AuditLog::query()->where('tabla_afectada', 'examen')->count());
    }

    public function test_un_auxiliar_no_puede_finalizar_ni_estando_habilitado_en_el_examen(): void
    {
        $exam = $this->createExam(['estado' => Exam::EN_CURSO], [$this->aulaId]);
        $auxiliaryId = '33333333-3333-4333-8333-000000000099';
        DB::table('usuario')->insert([
            'id_usuario' => $auxiliaryId,
            'nombre' => 'Auxiliar',
            'apellido_paterno' => 'Habilitado',
            'correo' => 'auxiliar.habilitado@test.com',
            'contrasenia' => 'x',
            'cod_sis' => '202400099',
            'estado' => 'ACTIVO',
        ]);
        $roleId = Role::firstOrCreate(
            ['nombre_rol' => Role::AUXILIAR],
            ['descripcion' => 'Auxiliar de docencia', 'estado' => 'ACTIVO']
        )->id_rol;
        DB::table('usuario_rol')->insert([
            'id_usuario' => $auxiliaryId,
            'id_rol' => $roleId,
            'fecha_inicio' => now(),
        ]);
        DB::table('examen_auxiliar')->insert([
            'id_examen' => $exam->id_examen,
            'id_usuario' => $auxiliaryId,
            'id_usuario_docente_habilita' => $this->docenteId,
            'id_ambiente' => $this->aulaId,
        ]);
        $this->actAsUserId($auxiliaryId);

        $this->postJson($this->url($exam))
            ->assertForbidden()
            ->assertJsonPath('message', 'Solo un docente puede finalizar un examen.');

        $this->assertSame(Exam::EN_CURSO, $exam->fresh()->estado);
        $this->assertSame(0, AuditLog::query()->where('tabla_afectada', 'examen')->count());
    }

    public function test_finalizacion_requiere_autenticacion(): void
    {
        $exam = $this->createExam(['estado' => Exam::EN_CURSO]);
        $this->actAsGuest();

        $this->postJson($this->url($exam))->assertUnauthorized();
        $this->assertSame(Exam::EN_CURSO, $exam->fresh()->estado);
    }

    public function test_docente_invitado_vigente_puede_finalizar_su_examen(): void
    {
        $exam = $this->createExam(['estado' => Exam::EN_CURSO]);
        $guestRoleId = Role::firstOrCreate(
            ['nombre_rol' => Role::INVITADO],
            ['descripcion' => 'Docente invitado', 'estado' => 'ACTIVO']
        )->id_rol;
        DB::table('usuario_rol')->where('id_usuario', $this->docenteId)->whereNull('fecha_fin')
            ->update(['fecha_fin' => now()]);
        DB::table('usuario')->where('id_usuario', $this->docenteId)->update(['cod_sis' => 'INV4821']);
        DB::table('usuario_rol')->insert([
            'id_usuario' => $this->docenteId,
            'id_rol' => $guestRoleId,
            'fecha_inicio' => now(),
        ]);
        $this->actAsUserId($this->docenteId);

        $this->postJson($this->url($exam))->assertOk()->assertJsonPath('data.estado', Exam::FINALIZADO);
    }

    public function test_finalizacion_manual_rechaza_estados_distintos_de_en_curso(): void
    {
        foreach ([Exam::PROGRAMADO, Exam::EN_INGRESO, Exam::CANCELADO, Exam::FINALIZADO] as $state) {
            $exam = $this->createExam([
                'nombre_examen' => "Examen {$state}",
                'estado' => $state,
            ]);

            $this->postJson($this->url($exam))->assertStatus(409);
            $this->assertSame($state, $exam->fresh()->estado);
        }
    }

    public function test_job_finaliza_en_curso_exactamente_diez_minutos_despues_del_fin(): void
    {
        $timezone = config('sciem.zona_horaria');
        $deadline = Carbon::parse('2026-10-01 11:44:00', $timezone);
        $exam = $this->createExam([
            'fecha' => '2026-10-01',
            'hora_inicio' => '10:04',
            'hora_fin' => '11:34',
            'duracion' => 90,
            'estado' => Exam::EN_CURSO,
        ]);

        $service = app(ExamLifecycleService::class);
        $this->assertSame(0, $service->finishExpired($deadline->copy()->subMinutes(10)));
        $this->assertSame(0, $service->finishExpired($deadline->copy()->subSecond()));
        $this->assertSame(Exam::EN_CURSO, $exam->fresh()->estado);
        $this->assertNull($exam->fresh()->fin_real_en);

        Carbon::setTestNow($deadline);
        try {
            app(FinalizeExpiredExamsJob::class)->handle($service);
        } finally {
            Carbon::setTestNow();
        }

        $this->postJson($this->url($exam))->assertStatus(409);
        $this->assertSame(0, $service->finishExpired($deadline->copy()->addMinute()));
        $closed = $exam->fresh();
        $this->assertSame(Exam::FINALIZADO, $closed->estado);
        $this->assertTrue($closed->fin_real_en->equalTo($deadline));
        $this->assertTrue($closed->fin_automatico);
        $this->assertSame(1, AuditLog::query()->where('tabla_afectada', 'examen')->count());
    }

    public function test_job_no_sobrescribe_un_cierre_manual_entre_la_seleccion_y_el_bloqueo(): void
    {
        $exam = $this->createExam([
            'fecha' => '2026-10-01',
            'hora_inicio' => '10:04',
            'duracion' => 90,
            'estado' => Exam::EN_CURSO,
        ]);
        $deadline = Carbon::parse('2026-10-01 11:44:00', config('sciem.zona_horaria'));
        $this->partialMock(ExamTimingService::class, function ($mock) use ($exam, $deadline): void {
            $mock->shouldReceive('finishesAutomaticallyAt')->once()->andReturnUsing(
                function () use ($exam, $deadline): Carbon {
                    $this->postJson($this->url($exam))->assertOk();

                    return $deadline;
                }
            );
        });

        $this->assertSame(0, app(ExamLifecycleService::class)->finishExpired($deadline));
        $this->assertSame(Exam::FINALIZADO, $exam->fresh()->estado);
        $this->assertFalse($exam->fresh()->fin_automatico);
        $this->assertSame(1, AuditLog::query()->where('tabla_afectada', 'examen')->count());
    }

    public function test_job_corrige_programados_antiguos_y_finaliza_en_curso(): void
    {
        $attributes = [
            'fecha' => '2026-09-30',
            'hora_inicio' => '08:00',
            'hora_fin' => '09:00',
            'duracion' => 60,
        ];
        $programmed = $this->createExam($attributes + [
            'nombre_examen' => 'Programado antiguo',
            'estado' => Exam::PROGRAMADO,
        ]);
        $running = $this->createExam($attributes + [
            'nombre_examen' => 'En curso antiguo',
            'estado' => Exam::EN_CURSO,
        ]);
        $entry = $this->createExam($attributes + [
            'nombre_examen' => 'En ingreso antiguo',
            'estado' => Exam::EN_INGRESO,
        ]);

        $service = app(ExamLifecycleService::class);
        $timezone = config('sciem.zona_horaria');
        $this->assertSame(1, $service->finishExpired(Carbon::parse('2026-09-30 10:59:59', $timezone)));
        $this->assertSame(Exam::PROGRAMADO, $programmed->fresh()->estado);
        $this->assertSame(Exam::EN_INGRESO, $entry->fresh()->estado);

        $this->assertSame(2, $service->finishExpired(Carbon::parse('2026-10-01 12:00:00', $timezone)));
        $this->assertSame(Exam::FINALIZADO, $programmed->fresh()->estado);
        $this->assertSame(Exam::FINALIZADO, $running->fresh()->estado);
        $this->assertSame(Exam::FINALIZADO, $entry->fresh()->estado);
    }

    public function test_finalizacion_automatica_congela_esperados_y_audita(): void
    {
        $exam = $this->createExam([
            'fecha' => '2026-09-30',
            'hora_inicio' => '08:00',
            'hora_fin' => '09:00',
            'duracion' => 60,
            'estado' => Exam::EN_CURSO,
        ]);
        DB::table('grupo_examen')->insert([
            'id_examen' => $exam->id_examen,
            'id_grupo' => $this->grupoPropioId,
        ]);

        $finished = app(ExamLifecycleService::class)->finishExpired(
            Carbon::parse('2026-10-01 12:00:00', config('sciem.zona_horaria'))
        );

        $this->assertSame(1, $finished);
        $this->assertSame(Exam::FINALIZADO, $exam->fresh()->estado);
        $this->assertSame(2, DB::table('examen_estudiante')
            ->where('id_examen', $exam->id_examen)
            ->where('estado_ingreso', 'NO_INGRESO')
            ->count());

        $log = AuditLog::query()
            ->where('tabla_afectada', 'examen')
            ->where('nuevo_valor->id_examen', $exam->id_examen)
            ->sole();

        $this->assertSame('FINALIZAR', $log->accion->operacion);
        $this->assertSame('AUTOMATICA', $log->nuevo_valor['tipo_finalizacion']);
        $this->assertArrayNotHasKey('estudiantes_esperados', $log->nuevo_valor);
    }

    public function test_cierre_automatico_tardio_conserva_fin_programado_mas_diez_minutos(): void
    {
        $zone = config('sciem.zona_horaria');
        $exam = $this->createExam([
            'fecha' => '2026-10-01',
            'hora_inicio' => '10:04',
            'hora_fin' => '11:34',
            'duracion' => 90,
            'estado' => Exam::EN_CURSO,
        ]);
        DB::table('examen')->where('id_examen', $exam->id_examen)
            ->update(['inicio_real_en' => '2026-10-01 14:05:00+00']);
        DB::table('grupo_examen')->insert([
            'id_examen' => $exam->id_examen,
            'id_grupo' => $this->grupoPropioId,
        ]);

        // El planificador corre más de tres horas tarde.
        $late = Carbon::parse('2026-10-01 14:50:00', $zone);
        $this->assertSame(1, app(ExamLifecycleService::class)->finishExpired($late));

        $closed = $exam->fresh();
        $this->assertSame('2026-10-01T15:44:00+00:00', $closed->fin_real_en->utc()->toIso8601String());
        $this->assertTrue($closed->fin_automatico);

        $history = app(ExamHistoryService::class)->show($closed, $this->docenteId);
        $end = collect($history['linea_tiempo'])->firstWhere('tipo', 'fin');
        $this->assertSame('2026-10-01 11:44:00', $end['fecha_hora']);
        $this->assertSame('Automático', $end['detalle']);
        $this->assertTrue($history['fin_automatico']);
        $this->assertSame('2026-10-01T15:44:00+00:00', Carbon::parse($history['fin_real_en'])->utc()->toIso8601String());

        $header = app(FinalReportService::class)->build($closed, $this->docenteId)['cabecera'];
        $this->assertSame('10:05', $header['hora_inicio']);
        $this->assertSame('11:44', $header['hora_fin']);
    }

    public function test_cierre_de_recuperacion_tardio_usa_el_plazo_y_no_falla_sin_inicio_real(): void
    {
        $zone = config('sciem.zona_horaria');
        $exam = $this->createExam([
            'fecha' => '2026-10-01',
            'hora_inicio' => '10:04',
            'hora_fin' => '11:34',
            'duracion' => 90,
            'estado' => Exam::EN_INGRESO,
        ]);
        DB::table('grupo_examen')->insert([
            'id_examen' => $exam->id_examen,
            'id_grupo' => $this->grupoPropioId,
        ]);

        $this->assertSame(1, app(ExamLifecycleService::class)->finishExpired(
            Carbon::parse('2026-10-01 18:00:00', $zone)
        ));

        $closed = $exam->fresh();
        $this->assertNull($closed->inicio_real_en);
        $this->assertSame('2026-10-01T17:34:00+00:00', $closed->fin_real_en->utc()->toIso8601String());
        $this->assertTrue($closed->fin_automatico);

        $history = app(ExamHistoryService::class)->show($closed, $this->docenteId);
        $timeline = collect($history['linea_tiempo']);
        $start = $timeline->firstWhere('tipo', 'inicio');
        $end = $timeline->firstWhere('tipo', 'fin');
        $this->assertSame('Inicio programado del examen', $start['descripcion']);
        $this->assertSame('Horario previsto', $start['detalle']);
        $this->assertSame('2026-10-01 13:34:00', $end['fecha_hora']);
        $this->assertSame('Automático', $end['detalle']);
        $this->assertTrue($history['fin_automatico']);

        $header = app(FinalReportService::class)->build($closed, $this->docenteId)['cabecera'];
        $this->assertSame('10:04 (programada)', $header['hora_inicio']);
        $this->assertSame('13:34', $header['hora_fin']);
    }

    public function test_fin_manual_conserva_el_instante_real_aunque_el_plazo_sea_posterior(): void
    {
        $exam = $this->createExam([
            'fecha' => '2026-10-01',
            'hora_inicio' => '10:04',
            'hora_fin' => '11:34',
            'duracion' => 90,
            'estado' => Exam::EN_CURSO,
        ]);

        $manualAt = Carbon::parse('2026-10-01T15:10:30Z');
        Carbon::setTestNow($manualAt);
        try {
            $this->postJson($this->url($exam))->assertOk();
        } finally {
            Carbon::setTestNow();
        }

        $closed = $exam->fresh();
        $this->assertTrue($closed->fin_real_en->equalTo($manualAt));
        $this->assertFalse($closed->fin_automatico);
    }

    public function test_finalizacion_conserva_ingresos_existentes(): void
    {
        $exam = $this->createExam(['estado' => Exam::EN_CURSO], [$this->aulaId]);
        DB::table('grupo_examen')->insert([
            'id_examen' => $exam->id_examen,
            'id_grupo' => $this->grupoPropioId,
        ]);

        $students = DB::table('grupo_estudiante')
            ->where('id_grupo', $this->grupoPropioId)
            ->orderBy('id_estudiante')
            ->pluck('id_estudiante');

        DB::table('examen_estudiante')->insert([
            [
                'id_examen' => $exam->id_examen,
                'id_estudiante' => $students[0],
                'id_grupo' => $this->grupoPropioId,
                'estado_habilitacion' => 'HABILITADO',
                'estado_ingreso' => 'INGRESO',
                'hora_ingreso' => '08:05:00',
            ],
            [
                'id_examen' => $exam->id_examen,
                'id_estudiante' => $students[1],
                'id_grupo' => $this->grupoPropioId,
                'estado_habilitacion' => 'HABILITADO',
                'estado_ingreso' => 'CON_RETRASO',
                'hora_ingreso' => '08:30:00',
            ],
        ]);

         $response = $this->postJson($this->url($exam));

         $response->assertOk()
         ->assertJsonPath('data.estado', Exam::FINALIZADO);

        $this->assertArrayNotHasKey('estadisticas', $response->json('data'));

        $this->assertSame(2, DB::table('examen_estudiante')
            ->where('id_examen', $exam->id_examen)
            ->count());
        $this->assertSame(1, DB::table('examen_estudiante')
            ->where('id_examen', $exam->id_examen)
            ->where('estado_ingreso', 'INGRESO')
            ->count());
        $this->assertSame(1, DB::table('examen_estudiante')
            ->where('id_examen', $exam->id_examen)
            ->where('estado_ingreso', 'CON_RETRASO')
            ->count());

        $counts = app(ExamParticipantService::class)->counts((int) $exam->id_examen);
        $this->assertSame(2, $counts['esperados']);
        $this->assertSame(2, $counts['ingresados']);
        $this->assertSame(0, $counts['pendientes']);
    }

    public function test_finalizacion_automatica_continua_si_un_examen_falla_al_auditar(): void
    {
        $attributes = [
            'fecha' => '2026-09-30',
            'hora_inicio' => '08:00',
            'hora_fin' => '09:00',
            'duracion' => 60,
            'estado' => Exam::EN_CURSO,
        ];

        $failedExam = $this->createExam($attributes + [
            'nombre_examen' => 'Examen con error',
        ]);
        $successfulExam = $this->createExam($attributes + [
            'nombre_examen' => 'Examen siguiente',
        ]);

        $attempts = 0;
        $this->mock(AuditLogService::class, function ($mock) use (&$attempts): void {
            $mock->shouldReceive('registrar')
                ->twice()
                ->andReturnUsing(function () use (&$attempts): void {
                    $attempts++;
                    if ($attempts === 1) {
                        throw new \RuntimeException('Fallo simulado de auditoria');
                    }
                });
        });

        $finished = app(ExamLifecycleService::class)->finishExpired(
            Carbon::parse('2026-10-01 12:00:00', config('sciem.zona_horaria'))
        );

        $this->assertSame(2, $attempts);
        $this->assertSame(1, $finished);
        $this->assertSame(Exam::EN_CURSO, $failedExam->fresh()->estado);
        $this->assertSame(Exam::FINALIZADO, $successfulExam->fresh()->estado);
    }

    public function test_finalizar_examen_inexistente_responde_404(): void
    {
        $this->postJson('/api/examenes/999999/finalizar')->assertNotFound();
    }
}
