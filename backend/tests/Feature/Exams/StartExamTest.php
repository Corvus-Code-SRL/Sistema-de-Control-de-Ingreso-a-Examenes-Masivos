<?php

namespace Tests\Feature\Exams;

use App\Models\Exam;
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

        $lifecycle->startExpired($openingAt->copy()->subSecond());
        $this->assertSame(Exam::PROGRAMADO, $exam->fresh()->estado);

        $lifecycle->startExpired($openingAt);
        $this->assertSame(Exam::PROGRAMADO, $exam->fresh()->estado);

        $lifecycle->startExpired($startsAt->copy()->subSecond());
        $this->assertSame(Exam::PROGRAMADO, $exam->fresh()->estado);

        $lifecycle->startExpired($startsAt);
        $this->assertSame(Exam::EN_CURSO, $exam->fresh()->estado);
        $this->assertTrue($exam->fresh()->inicio_real_en->equalTo($startsAt));
        $this->assertSame('01:35', $scheduledEnd->format('H:i'));
        $this->assertFalse($timing->hasEnded($exam, $startsAt));
    }

    public function test_el_ingreso_ordinario_no_ofrece_inicio_manual_del_examen(): void
    {
        $this->seedExamCatalog();
        $exam = $this->createExam(['estado' => Exam::PROGRAMADO]);

        $this->postJson("/api/examenes/{$exam->id_examen}/iniciar")->assertNotFound();
        $this->assertSame(Exam::PROGRAMADO, $exam->fresh()->estado);
    }
}
