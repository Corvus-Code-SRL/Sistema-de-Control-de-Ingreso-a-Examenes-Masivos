<?php

namespace Tests\Feature\Incidents;

use App\Models\Exam;
use App\Models\User;
use App\Services\Incidents\ExamInProgressSituationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsExamSituations;
use Tests\TestCase;

class ExamInProgressSituationsTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsExamSituations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedExamSituations();
    }

    private function inProgressUrl(): string
    {
        return "/api/examenes/{$this->situationExam->id_examen}/en-curso";
    }

    public function test_transfer_and_identity_reach_the_real_in_progress_endpoint(): void
    {
        $this->postJson($this->situationUrl(), $this->situationPayload())->assertCreated();
        $this->postJson($this->situationUrl(), [
            'tipo' => 'IDENTIFICACION',
            'motivo' => 'Documento deteriorado.',
        ])->assertCreated();

        $response = $this->getJson($this->inProgressUrl())->assertOk()
            ->assertJsonPath('data.total_situaciones', 2)
            ->assertJsonPath('data.situaciones_habilitadas', true);
        $student = collect($response->json('data.estudiantes'))
            ->firstWhere('id_estudiante', (int) $this->situationStudent->id_estudiante);

        $this->assertSame($this->otraAulaId, $student['id_ambiente']);
        $this->assertSame('INGRESO', $student['estado_ingreso']);
        $this->assertSame('08:00', $student['hora_ingreso']);
        $this->assertTrue($student['situaciones']['identidad_observada']);
        $this->assertSame(
            $this->aulaId,
            $student['situaciones']['ultimo_cambio_ambiente']['ambiente_origen']['id_ambiente']
        );
    }

    public function test_history_revision_changes_for_repeated_identity_observations(): void
    {
        $this->createSituationFixture();
        $this->getJson($this->inProgressUrl())->assertOk()->assertJsonPath('data.total_situaciones', 1);
        $this->createSituationFixture();
        $this->getJson($this->inProgressUrl())->assertOk()->assertJsonPath('data.total_situaciones', 2);
    }

    public function test_outgoing_transfer_refreshes_assistant_history_after_student_disappears(): void
    {
        $this->actAsSituationAssistant($this->aulaId);
        $this->postJson($this->situationUrl(), $this->situationPayload())->assertCreated();
        // Un hecho ajeno al aula de origen no debe cambiar su revisión ni filtrar información.
        $this->createSituationFixture(['id_ambiente_origen' => $this->otraAulaId]);
        $response = $this->getJson($this->inProgressUrl())->assertOk()
            ->assertJsonPath('data.total_situaciones', 1);

        $this->assertNotContains(
            (int) $this->situationStudent->id_estudiante,
            array_column($response->json('data.estudiantes'), 'id_estudiante')
        );
        $this->getJson($this->situationListUrl())->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_summary_is_not_limited_to_the_first_twenty_history_records(): void
    {
        $change = $this->createSituationFixture([
            'tipo' => 'CAMBIO_AMBIENTE',
            'id_ambiente_destino' => $this->otraAulaId,
            'motivo' => 'Ruido',
        ]);
        for ($index = 0; $index < 21; $index++) {
            $this->createSituationFixture();
        }
        $response = $this->getJson($this->inProgressUrl())->assertOk()
            ->assertJsonPath('data.total_situaciones', 22);
        $student = collect($response->json('data.estudiantes'))
            ->firstWhere('id_estudiante', (int) $this->situationStudent->id_estudiante);
        $this->assertTrue($student['situaciones']['identidad_observada']);
        $this->assertSame(
            (int) $change->id_situacion,
            $student['situaciones']['ultimo_cambio_ambiente']['id_situacion']
        );
    }

    public function test_summary_accepts_more_than_two_hundred_ids_in_batches(): void
    {
        $this->createSituationFixture();
        $id = (int) $this->situationStudent->id_estudiante;
        $ids = array_merge(range(1000000000, 1000000200), [$id]);
        $result = app(ExamInProgressSituationService::class)->forExam(
            $this->situationExam,
            $ids,
            User::query()->findOrFail($this->docenteId)
        );
        $this->assertTrue($result['students'][$id]['identidad_observada']);
        $this->assertCount(1, $result['students']);
    }

    public function test_finalized_exam_keeps_history_and_rejects_new_situations(): void
    {
        $this->createSituationFixture();
        DB::table('examen')->where('id_examen', $this->situationExam->id_examen)
            ->update(['estado' => Exam::FINALIZADO]);
        $this->getJson($this->inProgressUrl())->assertOk()
            ->assertJsonPath('data.estado', Exam::FINALIZADO)
            ->assertJsonPath('data.total_situaciones', 1);
        $this->postJson($this->situationUrl(), $this->situationPayload())->assertStatus(409);
    }

    public function test_unrelated_teacher_cannot_read_the_integrated_history(): void
    {
        $this->actAsTeacher($this->otroDocenteId);
        $this->getJson($this->inProgressUrl())->assertForbidden();
    }
}