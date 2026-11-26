<?php

namespace Tests\Feature\Exams\RoomDistribution;

use App\Models\Exam;
use App\Services\Exams\ExamPreparationWindow;
use App\Services\Exams\ExamTimingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsRoomDistribution;
use Tests\TestCase;

/** HU-031 CA-9: la edición cierra a la hora de apertura aunque el job de apertura no haya corrido. */
class RoomDistributionOpeningTimeTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsRoomDistribution;

    private Exam $exam;
    private Carbon $opensAt;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedExamCatalog();
        $this->exam = $this->distributionExam(4, [3, 3]);
        DB::table('examen')->where('id_examen', $this->exam->id_examen)->update(['minutos_apertura' => 15]);
        $this->opensAt = app(ExamTimingService::class)->opensAt($this->exam->fresh());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_un_minuto_antes_de_la_apertura_aun_se_edita(): void
    {
        Carbon::setTestNow($this->opensAt->copy()->subSecond());

        $this->getJson($this->distributionUrl($this->exam))->assertOk()->assertJsonPath('data.editable', true);
        $this->saveDistribution($this->exam)->assertOk();
    }

    public function test_a_la_hora_de_apertura_se_bloquea_sin_ingreso_abierto(): void
    {
        Carbon::setTestNow($this->opensAt->copy()->subMinute());
        $version = $this->distributionVersion($this->exam);
        $assignments = $this->distributionProposal($this->exam);
        Carbon::setTestNow($this->opensAt->copy());

        $this->assertNull($this->exam->fresh()->ingreso_abierto_en);
        $this->getJson($this->distributionUrl($this->exam))->assertOk()
            ->assertJsonPath('data.editable', false)
            ->assertJsonPath('data.motivo_solo_lectura', ExamPreparationWindow::CLOSED_MESSAGE);
        $this->putJson($this->distributionUrl($this->exam), ['version' => $version, 'asignaciones' => $assignments])
            ->assertStatus(409);
        $this->postJson($this->distributionUrl($this->exam) . '/propuesta')->assertStatus(409);
        $this->assertDatabaseMissing('distribucion_ambiente', ['id_examen' => $this->exam->id_examen]);
    }
}
