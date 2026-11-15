<?php

namespace Tests\Feature\Exams;

use App\Models\Exam;
use App\Services\Exams\ExamLifecycleService;
use App\Services\Exams\ExamTimingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\SeedsExamCatalog;
use Tests\TestCase;

class StartExamTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsExamCatalog;

    public function test_el_examen_inicia_con_los_minutos_de_apertura_sin_adelantar_su_fin(): void
    {
        $this->seedExamCatalog();
        $exam = $this->createExam([
            'fecha' => $this->futureDate(),
            'hora_inicio' => '00:05',
        ])->fresh();
        $timing = app(ExamTimingService::class);
        $openingAt = $timing->opensAt($exam);
        $scheduledEnd = $timing->endsAt($exam);
        $this->assertSame('23:55', $openingAt->format('H:i'));
        $lifecycle = app(ExamLifecycleService::class);

        $lifecycle->startExpired($openingAt->copy()->subSecond());
        $this->assertSame(Exam::PROGRAMADO, $exam->fresh()->estado);

        $lifecycle->startExpired($openingAt);
        $this->assertSame(Exam::EN_CURSO, $exam->fresh()->estado);
        $this->assertTrue($exam->fresh()->inicio_real_en->equalTo($openingAt));
        $this->assertSame('01:35', $scheduledEnd->format('H:i'));
        $this->assertFalse($timing->hasEnded($exam, $openingAt));
    }

    public function test_el_ingreso_ordinario_no_ofrece_inicio_manual_del_examen(): void
    {
        $this->seedExamCatalog();
        $exam = $this->createExam(['estado' => Exam::PROGRAMADO]);

        $this->postJson("/api/examenes/{$exam->id_examen}/iniciar")->assertNotFound();
        $this->assertSame(Exam::PROGRAMADO, $exam->fresh()->estado);
    }
}
