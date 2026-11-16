<?php

namespace Tests\Feature\Exams;

use App\Models\Exam;
use App\Services\EntryControl\CloseOrdinaryEntryService;
use App\Services\EntryControl\RoomAssignmentService;
use App\Services\Exams\ExamLifecycleService;
use App\Services\Exams\ExamTimingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsExamCatalog;
use Tests\TestCase;

class StartExamTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsExamCatalog;

    /** Fila 1 del modelo: antes de abrir el control el examen está PROGRAMADO y nadie entra. */
    public function test_antes_de_abrir_el_control_el_examen_esta_programado_y_no_se_inicia(): void
    {
        $this->seedExamCatalog();
        $exam = $this->createExam(['fecha' => $this->futureDate(), 'hora_inicio' => '00:05'], [$this->aulaId])->fresh();

        app(ExamLifecycleService::class)->startExpired(app(ExamTimingService::class)->startsAt($exam));

        $fresh = $exam->fresh();
        $this->assertSame(Exam::PROGRAMADO, $fresh->estado);
        $this->assertNull($fresh->ingreso_abierto_en);
        $this->assertFalse(CloseOrdinaryEntryService::isOpen($fresh));
    }

    /** Filas 2 y 3: la apertura no cambia el estado; en hora_inicio pasa a EN_CURSO con el ingreso aún abierto. */
    public function test_la_apertura_anticipada_no_inicia_el_examen_antes_de_su_hora(): void
    {
        $this->seedExamCatalog();
        $exam = $this->createExam([
            'fecha' => $this->futureDate(),
            'hora_inicio' => '00:05',
        ], [$this->aulaId])->fresh();
        DB::table('grupo_examen')->insert(['id_examen' => $exam->id_examen, 'id_grupo' => $this->grupoPropioId]);
        app(RoomAssignmentService::class)->prepare((int) $exam->id_examen);
        $timing = app(ExamTimingService::class);
        $openingAt = $timing->opensAt($exam);
        $startsAt = $timing->startsAt($exam);
        $scheduledEnd = $timing->endsAt($exam);
        $this->assertSame('23:55', $openingAt->format('H:i'));
        $lifecycle = app(ExamLifecycleService::class);

        $opened = $exam->fresh();
        $this->assertSame(Exam::PROGRAMADO, $opened->estado);
        $this->assertNotNull($opened->ingreso_abierto_en);
        $this->assertNull($opened->ingreso_ordinario_cerrado_en);
        $this->assertTrue(CloseOrdinaryEntryService::isOpen($opened));

        $lifecycle->startExpired($openingAt->copy()->subSecond());
        $this->assertSame(Exam::PROGRAMADO, $exam->fresh()->estado);

        $lifecycle->startExpired($openingAt);
        $this->assertSame(Exam::PROGRAMADO, $exam->fresh()->estado);

        $lifecycle->startExpired($startsAt->copy()->subSecond());
        $this->assertSame(Exam::PROGRAMADO, $exam->fresh()->estado);

        $lifecycle->startExpired($startsAt);
        $started = $exam->fresh();
        $this->assertSame(Exam::EN_CURSO, $started->estado);
        $this->assertNull($started->inicio_real_en);
        $this->assertTrue($started->ingreso_abierto_en->equalTo($opened->ingreso_abierto_en));
        $this->assertNull($started->ingreso_ordinario_cerrado_en);
        $this->assertTrue(CloseOrdinaryEntryService::isOpen($started));
        $this->assertSame('01:35', $scheduledEnd->format('H:i'));
        $this->assertFalse($timing->hasEnded($exam, $startsAt));

        $payload = json_decode((string) DB::table('log as l')
            ->join('accion as ac', 'ac.id_accion', '=', 'l.id_accion')
            ->where('ac.operacion', 'INICIAR')
            ->whereRaw("l.nuevo_valor->>'id_examen' = ?", [(string) $exam->id_examen])
            ->value('l.nuevo_valor'), true);
        $this->assertSame('AUTOMATICO', $payload['tipo_inicio']);
        $this->assertArrayNotHasKey('inicio_real_en', $payload);
    }

    /** Fila 4: «Cerrar ingreso ordinario» deja el examen EN_CURSO y solo admite el ingreso con retraso. */
    public function test_cerrar_el_ingreso_ordinario_mantiene_en_curso_y_habilita_el_retraso(): void
    {
        $exam = $this->examInProgressWithEntryOpen();
        $this->actAsTeacher($this->docenteId);

        $this->postJson("/api/control-ingreso/examenes/{$exam->id_examen}/cerrar-ingreso-ordinario")->assertOk();

        $closed = $exam->fresh();
        $this->assertSame(Exam::EN_CURSO, $closed->estado);
        $this->assertNotNull($closed->ingreso_ordinario_cerrado_en);
        $this->assertFalse(CloseOrdinaryEntryService::isOpen($closed));
        $this->assertTrue(CloseOrdinaryEntryService::allowsLate($closed));
    }

    /** Fila 5: al terminar con el ingreso abierto, el cierre queda en la hora de término. */
    public function test_finalizar_con_el_ingreso_abierto_lo_cierra_en_la_hora_de_termino(): void
    {
        $exam = $this->examInProgressWithEntryOpen();
        $this->actAsTeacher($this->docenteId);

        $this->postJson("/api/examenes/{$exam->id_examen}/finalizar")->assertOk();

        $finished = $exam->fresh();
        $this->assertSame(Exam::FINALIZADO, $finished->estado);
        $this->assertNotNull($finished->fin_real_en);
        $this->assertTrue($finished->ingreso_ordinario_cerrado_en->equalTo($finished->fin_real_en));
        $this->assertFalse(CloseOrdinaryEntryService::isOpen($finished));
        $this->assertFalse(CloseOrdinaryEntryService::allowsLate($finished));
    }

    public function test_finalizar_con_el_ingreso_ya_cerrado_conserva_la_hora_de_cierre(): void
    {
        $exam = $this->examInProgressWithEntryOpen();
        $this->actAsTeacher($this->docenteId);
        $this->postJson("/api/control-ingreso/examenes/{$exam->id_examen}/cerrar-ingreso-ordinario")->assertOk();
        $closedAt = $exam->fresh()->ingreso_ordinario_cerrado_en;

        $this->postJson("/api/examenes/{$exam->id_examen}/finalizar")->assertOk();

        $this->assertTrue($exam->fresh()->ingreso_ordinario_cerrado_en->equalTo($closedAt));
    }

    public function test_el_ingreso_ordinario_no_ofrece_inicio_manual_del_examen(): void
    {
        $this->seedExamCatalog();
        $exam = $this->createExam(['estado' => Exam::PROGRAMADO]);

        $this->postJson("/api/examenes/{$exam->id_examen}/iniciar")->assertNotFound();
        $this->assertSame(Exam::PROGRAMADO, $exam->fresh()->estado);
    }

    /** Examen que pasó por la apertura real del control y el inicio programado. */
    private function examInProgressWithEntryOpen(): Exam
    {
        $this->seedExamCatalog();
        $exam = $this->createExam([
            'fecha' => $this->futureDate(),
            'hora_inicio' => '00:05',
        ], [$this->aulaId])->fresh();
        DB::table('grupo_examen')->insert(['id_examen' => $exam->id_examen, 'id_grupo' => $this->grupoPropioId]);
        app(RoomAssignmentService::class)->prepare((int) $exam->id_examen);
        app(ExamLifecycleService::class)->startExpired(app(ExamTimingService::class)->startsAt($exam));
        $this->assertSame(Exam::EN_CURSO, $exam->fresh()->estado);

        return $exam;
    }
}
