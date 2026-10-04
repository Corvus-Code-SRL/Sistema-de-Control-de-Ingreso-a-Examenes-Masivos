<?php

namespace Tests\Feature\Exams;

use App\Jobs\FinalizeExpiredExamsJob;
use App\Models\Action;
use App\Models\AuditLog;
use App\Models\Exam;
use App\Models\Role;
use App\Services\EntryControl\EntryControlSnapshotService;
use App\Services\EntryControl\RoomAssignmentService;
use App\Services\Exams\ExamLifecycleService;
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

    public function test_docente_creador_finaliza_examen_abierto_desactiva_redis_y_audita(): void
    {
        $exam = $this->createExam(['estado' => Exam::EN_INGRESO], [$this->aulaId]);
        DB::table('grupo_examen')->insert([
            'id_examen' => $exam->id_examen,
            'id_grupo' => $this->grupoPropioId,
        ]);
        app(RoomAssignmentService::class)->prepare((int) $exam->id_examen);
        $this->assertTrue(app(EntryControlSnapshotService::class)->ready((int) $exam->id_examen));

        $this->postJson($this->url($exam))
            ->assertOk()
            ->assertJsonPath('mensaje', 'Examen finalizado correctamente.')
            ->assertJsonPath('data.estado', Exam::FINALIZADO);

        $this->assertSame(Exam::FINALIZADO, $exam->fresh()->estado);
        $this->assertFalse(app(EntryControlSnapshotService::class)->ready((int) $exam->id_examen));

        $log = AuditLog::query()->where('tabla_afectada', 'examen')->sole();
        $this->assertSame(
            'FINALIZAR',
            Action::query()->whereKey($log->id_accion)->value('operacion')
        );
        $this->assertSame($this->docenteId, $log->id_usuario);
        $this->assertEquals(
            ['id_examen' => $exam->id_examen, 'estado' => Exam::EN_INGRESO],
            $log->antiguo_valor
        );
        $this->assertEquals(
            ['id_examen' => $exam->id_examen, 'estado' => Exam::FINALIZADO],
            $log->nuevo_valor
        );
    }

    public function test_solo_docente_creador_puede_finalizar(): void
    {
        $exam = $this->createExam([
            'estado' => Exam::EN_INGRESO,
            'id_usuario_docente' => $this->otroDocenteId,
        ]);

        $this->postJson($this->url($exam))->assertForbidden();

        $this->assertSame(Exam::EN_INGRESO, $exam->fresh()->estado);
        $this->assertSame(0, AuditLog::query()->where('tabla_afectada', 'examen')->count());
    }

    public function test_un_auxiliar_no_puede_finalizar_ni_estando_habilitado_en_el_examen(): void
    {
        $exam = $this->createExam(['estado' => Exam::EN_INGRESO], [$this->aulaId]);
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
            ->assertJsonPath('message', 'Un auxiliar no puede finalizar un examen.');

        $this->assertSame(Exam::EN_INGRESO, $exam->fresh()->estado);
        $this->assertSame(0, AuditLog::query()->where('tabla_afectada', 'examen')->count());
    }

    public function test_finalizacion_manual_rechaza_estados_no_activos(): void
    {
        foreach ([Exam::PROGRAMADO, Exam::CANCELADO, Exam::FINALIZADO] as $state) {
            $exam = $this->createExam([
                'nombre_examen' => "Examen {$state}",
                'estado' => $state,
            ]);

            $this->postJson($this->url($exam))->assertStatus(409);
            $this->assertSame($state, $exam->fresh()->estado);
        }
    }

    public function test_job_espera_dos_horas_despues_del_fin_y_luego_finaliza(): void
    {
        $timezone = config('sciem.zona_horaria');
        $before = Carbon::parse('2026-10-01 14:59:00', $timezone);
        $exam = $this->createExam([
            'fecha' => '2026-10-01',
            'hora_inicio' => '12:00',
            'hora_fin' => '13:00',
            'duracion' => 60,
            'estado' => Exam::EN_INGRESO,
        ]);

        $service = app(ExamLifecycleService::class);
        $this->assertSame(0, $service->finishExpired($before));
        $this->assertSame(Exam::EN_INGRESO, $exam->fresh()->estado);

        Carbon::setTestNow(Carbon::parse('2026-10-01 15:00:00', $timezone));
        try {
            app(FinalizeExpiredExamsJob::class)->handle($service);
        } finally {
            Carbon::setTestNow();
        }

        $this->assertSame(Exam::FINALIZADO, $exam->fresh()->estado);
        $this->assertSame(0, $service->finishExpired(Carbon::parse('2026-10-01 15:01:00', $timezone)));
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

        $finished = app(ExamLifecycleService::class)->finishExpired(
            Carbon::parse('2026-10-01 12:00:00', config('sciem.zona_horaria'))
        );

        $this->assertSame(2, $finished);
        $this->assertSame(Exam::FINALIZADO, $programmed->fresh()->estado);
        $this->assertSame(Exam::FINALIZADO, $running->fresh()->estado);
    }

    public function test_finalizar_examen_inexistente_responde_404(): void
    {
        $this->postJson('/api/examenes/999999/finalizar')->assertNotFound();
    }
}
