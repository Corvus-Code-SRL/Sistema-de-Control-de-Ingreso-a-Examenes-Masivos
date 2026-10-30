<?php

namespace Tests\Feature\Incidents;

use App\Models\User;
use App\Services\Incidents\ExamSituationSummaryService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsExamSituations;
use Tests\TestCase;

class ExamSituationSummaryTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsExamSituations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedExamSituations();
    }

    public function testReturnsDefaultsWithoutSituations(): void
    {
        $id = (int) $this->situationStudent->id_estudiante;
        $result = $this->summaryFor($this->docenteId);
        $this->assertSame(['identidad_observada' => false, 'ultimo_cambio_ambiente' => null], $result[$id]);
    }

    public function testObservationAndLatestRoomChangeCoexistBeyondOneHistoryPage(): void
    {
        $this->createSituationFixture([
            'tipo' => 'CAMBIO_AMBIENTE',
            'id_ambiente_destino' => $this->otraAulaId,
            'motivo' => 'Ruido',
        ]);
        $latest = $this->createSituationFixture([
            'tipo' => 'CAMBIO_AMBIENTE',
            'id_ambiente_origen' => $this->otraAulaId,
            'id_ambiente_destino' => $this->aulaId,
            'motivo' => 'Retorno',
        ]);

        for ($index = 0; $index < 21; $index++) {
            $this->createSituationFixture(['registrado_en' => '2026-10-27 12:40:00+00:00']);
        }

        $id = (int) $this->situationStudent->id_estudiante;
        $result = $this->summaryFor($this->docenteId);
        $this->assertTrue($result[$id]['identidad_observada']);
        $this->assertSame((int) $latest->id_situacion, $result[$id]['ultimo_cambio_ambiente']['id_situacion']);
        $this->assertSame($this->otraAulaId, $result[$id]['ultimo_cambio_ambiente']['ambiente_origen']['id_ambiente']);
    }

    public function testAssistantCannotReadSummaryAfterStudentLeavesTheirRoom(): void
    {
        $this->actAsSituationAssistant($this->aulaId);
        DB::table('examen_estudiante')->where('id_examen', $this->situationExam->id_examen)
            ->where('id_estudiante', $this->situationStudent->id_estudiante)
            ->update(['id_ambiente' => $this->otraAulaId]);
        $this->assertSame([], $this->summaryFor($this->otroDocenteId));
    }

    public function testUnrelatedTeacherCannotReadSummaries(): void
    {
        $this->expectException(AuthorizationException::class);
        $this->summaryFor($this->otroDocenteId);
    }

    private function summaryFor(string $actorId): array
    {
        return app(ExamSituationSummaryService::class)->forStudents(
            (int) $this->situationExam->id_examen,
            [(int) $this->situationStudent->id_estudiante],
            User::query()->findOrFail($actorId)
        );
    }
}