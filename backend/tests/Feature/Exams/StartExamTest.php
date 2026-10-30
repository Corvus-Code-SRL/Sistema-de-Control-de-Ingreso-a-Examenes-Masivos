<?php

namespace Tests\Feature\Exams;

use App\Models\Action;
use App\Models\AuditLog;
use App\Models\Exam;
use App\Services\EntryControl\EntryConfirmationService;
use App\Services\Exams\ExamLifecycleService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\SeedsExamCatalog;
use Tests\TestCase;

class StartExamTest extends TestCase
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
        return "/api/examenes/{$exam->id_examen}/iniciar";
    }

    public function test_docente_inicia_examen_en_ingreso_y_se_audita(): void
    {
        $exam = $this->createExam(['estado' => Exam::EN_INGRESO]);

        $this->postJson($this->url($exam))
            ->assertOk()
            ->assertJsonPath('mensaje', 'Examen iniciado correctamente.')
            ->assertJsonPath('data.estado', Exam::EN_CURSO)
            ->assertJsonPath('data.inicio_automatico', false);

        $exam->refresh();
        $this->assertNotNull($exam->inicio_real_en);
        $this->assertSame(Exam::EN_CURSO, $exam->estado);

        $log = AuditLog::query()->where('tabla_afectada', 'examen')->sole();
        $this->assertSame('INICIAR', Action::query()->whereKey($log->id_accion)->value('operacion'));
        $this->assertSame($this->docenteId, $log->id_usuario);
    }

    public function test_no_se_puede_iniciar_dos_veces(): void
    {
        $exam = $this->createExam(['estado' => Exam::EN_INGRESO]);

        $this->postJson($this->url($exam))->assertOk();
        $this->postJson($this->url($exam))->assertStatus(409);
        $this->assertSame(1, AuditLog::query()->where('tabla_afectada', 'examen')->count());
    }

    public function test_docente_ajeno_no_puede_iniciar(): void
    {
        $exam = $this->createExam([
            'estado' => Exam::EN_INGRESO,
            'id_usuario_docente' => $this->otroDocenteId,
        ]);

        $this->postJson($this->url($exam))->assertForbidden();
        $this->assertSame(Exam::EN_INGRESO, $exam->fresh()->estado);
    }

    public function test_inicio_automatico_a_los_diez_minutos_sin_duplicar_bitacora(): void
    {
        $zone = config('sciem.zona_horaria');
        $exam = $this->createExam([
            'fecha' => '2026-10-28',
            'hora_inicio' => '08:00',
            'hora_fin' => '09:30',
            'duracion' => 90,
            'estado' => Exam::EN_INGRESO,
        ]);
        $lifecycle = app(ExamLifecycleService::class);

        $this->assertSame(0, $lifecycle->startExpired(Carbon::parse('2026-10-28 08:09:00', $zone)));
        $this->assertSame(Exam::EN_INGRESO, $exam->fresh()->estado);
        $this->assertSame(1, $lifecycle->startExpired(Carbon::parse('2026-10-28 08:10:00', $zone)));
        $this->assertSame(0, $lifecycle->startExpired(Carbon::parse('2026-10-28 08:11:00', $zone)));

        $exam->refresh();
        $this->assertSame(Exam::EN_CURSO, $exam->estado);
        $this->assertTrue($exam->inicio_automatico);
        $this->assertSame('08:10', $exam->inicio_real_en->setTimezone($zone)->format('H:i'));
        $log = AuditLog::query()->where('tabla_afectada', 'examen')->sole();
        $this->assertSame('INICIAR', $log->accion->operacion);
        $this->assertSame('AUTOMATICO', $log->nuevo_valor['tipo_inicio']);
    }

    public function test_confirmacion_rechaza_un_examen_ya_iniciado_aun_con_modelo_obsoleto(): void
    {
        $exam = $this->createExam(['estado' => Exam::EN_INGRESO]);
        $studentId = DB::table('grupo_estudiante')
            ->where('id_grupo', $this->grupoPropioId)
            ->value('id_estudiante');
        $actor = $this->actAsTeacher($this->docenteId);
        $stale = clone $exam;
        $exam->update(['estado' => Exam::EN_CURSO]);

        $result = app(EntryConfirmationService::class)->confirm($stale, [
            'id_estudiante' => (int) $studentId,
            'id_ambiente' => $this->aulaId,
        ], $actor);

        $this->assertFalse($result['creado']);
        $this->assertSame('EXAMEN_INICIADO', $result['veredicto']);
        $this->assertSame(0, DB::table('examen_estudiante')
            ->where('id_examen', $exam->id_examen)->count());
    }

    public function test_solo_se_inicia_desde_en_ingreso(): void
    {
        foreach ([Exam::PROGRAMADO, Exam::EN_CURSO, Exam::FINALIZADO, Exam::CANCELADO] as $state) {
            $exam = $this->createExam(['estado' => $state]);
            $this->postJson($this->url($exam))->assertStatus(409);
            $this->assertSame($state, $exam->fresh()->estado);
        }
    }
}
