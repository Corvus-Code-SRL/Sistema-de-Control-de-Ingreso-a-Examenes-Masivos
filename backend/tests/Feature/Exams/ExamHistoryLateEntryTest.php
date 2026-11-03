<?php

namespace Tests\Feature\Exams;

use App\Models\Exam;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\SeedsLateEntryExam;
use Tests\TestCase;

/**
 * HU-052 con datos reales de HU-037: un retraso autorizado por el docente aparece en la
 * línea de tiempo del historial y en el total «con retraso» del examen finalizado.
 */
class ExamHistoryLateEntryTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsLateEntryExam;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedLateEntryExam();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_un_retraso_autorizado_aparece_en_la_linea_de_tiempo_y_en_el_total(): void
    {
        $this->insertEntry($this->firstStudentId, '10:01:00', $this->aulaId);

        $this->travelToLocal('10:12');
        $this->actAsUserId($this->assistantBId);
        $requestId = (int) $this->postJson($this->lateEntryUrl('solicitudes'), [
            'id_estudiante' => $this->secondStudentId,
            'ci' => $this->studentColumn($this->secondStudentId, 'ci'),
            'id_ambiente' => $this->otraAulaId,
        ])->assertCreated()->json('data.id_solicitud');

        $this->travelToLocal('10:20');
        $this->actAsTeacher($this->docenteId);
        $this->postJson($this->lateEntryUrl("solicitudes/{$requestId}/autorizar"))->assertOk();
        $this->postJson("/api/examenes/{$this->exam->id_examen}/finalizar")->assertOk();
        $this->assertSame(Exam::FINALIZADO, $this->exam->fresh()->estado);

        $this->getJson('/api/historial/examenes')->assertOk()
            ->assertJsonPath('data.examenes.0.esperados', 2)
            ->assertJsonPath('data.examenes.0.presentes', 2);
        $summary = $this->getJson('/api/historial/examenes/' . $this->exam->id_examen)->assertOk()
            ->assertJsonPath('data.totales.a_tiempo', 1)
            ->assertJsonPath('data.totales.con_retraso', 1)
            ->assertJsonPath('data.totales.ausentes', 0)
            ->assertJsonPath('data.totales.sin_clasificar', 0);

        $late = collect($summary->json('data.linea_tiempo'))->where('tipo', 'retraso')->values();
        $this->assertCount(1, $late);
        $this->assertSame('10:12:00', substr($late[0]['fecha_hora'], 11));
        $this->assertSame('Autorizó: Ana Rojas', $late[0]['detalle']);
        $this->assertStringContainsString('(692B)', $late[0]['descripcion']);
        $this->assertNotContains('ingreso', array_column($summary->json('data.linea_tiempo'), 'tipo'));
    }
}
