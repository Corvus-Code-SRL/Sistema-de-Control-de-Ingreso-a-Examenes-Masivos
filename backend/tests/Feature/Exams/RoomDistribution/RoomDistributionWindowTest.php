<?php

namespace Tests\Feature\Exams\RoomDistribution;

use App\Models\Exam;
use App\Services\Exams\ExamPreparationWindow;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsRoomDistribution;
use Tests\TestCase;

class RoomDistributionWindowTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsRoomDistribution;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedExamCatalog();
    }

    /** @dataProvider closedStates */
    public function test_ventana_cerrada_permanece_consultable_y_rechaza_propuesta_y_guardado(string $state): void
    {
        $exam = $this->distributionExam();
        $version = $this->distributionVersion($exam);
        $assignments = $this->distributionProposal($exam);
        $this->putExamInState((int) $exam->id_examen, $state);

        $this->getJson($this->distributionUrl($exam))->assertOk()->assertJsonPath('data.editable', false)
            ->assertJsonPath('data.motivo_solo_lectura', ExamPreparationWindow::CLOSED_MESSAGE);
        $this->postJson($this->distributionUrl($exam) . '/propuesta')->assertStatus(409);
        $this->putJson($this->distributionUrl($exam), [
            'version' => $version, 'asignaciones' => $assignments,
        ])->assertStatus(409);
        $this->assertSame(0, DB::table('distribucion_ambiente')->count());
    }

    /** @return array<string, array{0:string}> */
    public function closedStates(): array
    {
        return [
            'control abierto' => ['INGRESO_ABIERTO'],
            'en curso' => [Exam::EN_CURSO],
            'finalizado' => [Exam::FINALIZADO],
            'cancelado' => [Exam::CANCELADO],
        ];
    }
}
