<?php

namespace Tests\Feature\EntryControl;

use App\Models\LateEntryRequest;
use App\Models\StudentExam;
use App\Support\Exams\EntryRoomWarning;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\PlansExamRooms;
use Tests\Concerns\SeedsLateEntryExam;
use Tests\TestCase;

/** HU-031 CA-11 en las solicitudes de HU-037: el auxiliar y el docente confirman el aviso. */
class LateEntryRoomWarningRequestTest extends TestCase
{
    use DatabaseTransactions;
    use PlansExamRooms;
    use SeedsLateEntryExam;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedLateEntryExam();
        $this->planRooms((int) $this->exam->id_examen, [$this->firstStudentId => $this->aulaId]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_solicitud_y_autorizacion_exigen_confirmar_el_aviso(): void
    {
        $this->actAsUserId($this->assistantBId);
        $input = ['id_estudiante' => $this->firstStudentId, 'id_ambiente' => $this->otraAulaId];

        $this->postJson($this->lateEntryUrl('solicitudes'), $input)
            ->assertStatus(409)
            ->assertJsonPath('veredicto', EntryRoomWarning::VERDICT);
        $this->assertSame(0, LateEntryRequest::query()->count());
        $id = $this->postJson($this->lateEntryUrl('solicitudes'), $input + ['confirmar_aviso_ambiente' => true])
            ->assertCreated()->json('data.id_solicitud');

        $this->actAsUserId($this->docenteId);
        $this->postJson($this->lateEntryUrl("solicitudes/{$id}/autorizar"))->assertStatus(409);
        $this->postJson($this->lateEntryUrl("solicitudes/{$id}/autorizar"), ['confirmar_aviso_ambiente' => true])
            ->assertOk();

        $this->assertDatabaseHas('examen_estudiante', [
            'id_examen' => $this->exam->id_examen,
            'id_estudiante' => $this->firstStudentId,
            'estado_ingreso' => StudentExam::CON_RETRASO,
            'id_ambiente' => $this->otraAulaId,
            'id_ambiente_planificado' => $this->aulaId,
        ]);
    }
}
