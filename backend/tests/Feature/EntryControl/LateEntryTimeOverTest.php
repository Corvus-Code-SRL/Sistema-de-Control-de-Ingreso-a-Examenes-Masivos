<?php

namespace Tests\Feature\EntryControl;

use App\Exceptions\EntryControl\LateEntryException;
use App\Models\Exam;
use App\Models\LateEntryRequest;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\SeedsLateEntryExam;
use Tests\TestCase;

/**
 * HU-037: el ingreso con retraso termina a la hora de fin programada (10:00 + 120 min = 12:00),
 * aunque el examen siga EN_CURSO hasta su cierre automático.
 */
class LateEntryTimeOverTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsLateEntryExam;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedLateEntryExam();
        DB::table('examen')->where('id_examen', $this->exam->id_examen)->update([
            'fecha' => '2026-10-31',
            'hora_inicio' => '10:00',
            'hora_fin' => '12:00',
            'duracion' => 120,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function assertTimeOver(TestResponse $response): void
    {
        $response->assertStatus(409)
            ->assertJsonPath('veredicto', LateEntryException::TIME_OVER)
            ->assertJsonPath('message', LateEntryException::TIME_OVER_MESSAGE);
    }

    private function verify(): TestResponse
    {
        $this->actAsTeacher($this->docenteId);

        return $this->postJson($this->lateEntryUrl('verificar'), [
            'cod_sis' => $this->studentColumn($this->secondStudentId, 'cod_sis'),
        ]);
    }

    private function request(): TestResponse
    {
        $this->actAsUserId($this->assistantBId);

        return $this->postJson($this->lateEntryUrl('solicitudes'), [
            'id_estudiante' => $this->secondStudentId,
            'id_ambiente' => $this->otraAulaId,
        ]);
    }

    private function register(): TestResponse
    {
        $this->actAsTeacher($this->docenteId);

        return $this->postJson($this->lateEntryUrl('registrar'), [
            'id_estudiante' => $this->secondStudentId,
            'id_ambiente' => $this->otraAulaId,
        ]);
    }

    private function authorize(int $requestId): TestResponse
    {
        $this->actAsTeacher($this->docenteId);

        return $this->postJson($this->lateEntryUrl("solicitudes/{$requestId}/autorizar"));
    }

    public function test_verificar_se_permite_un_minuto_antes_y_se_rechaza_un_minuto_despues(): void
    {
        $this->travelToLocal('11:59');
        $this->verify()->assertOk()->assertJsonPath('data.autorizado', true);

        $this->travelToLocal('12:01');
        $this->assertTimeOver($this->verify());
    }

    public function test_solicitar_se_permite_un_minuto_antes_y_se_rechaza_un_minuto_despues(): void
    {
        $this->travelToLocal('12:01');
        $this->assertTimeOver($this->request());
        $this->assertSame(0, LateEntryRequest::query()->count());

        $this->travelToLocal('11:59');
        $this->request()->assertCreated();
    }

    public function test_registrar_se_permite_un_minuto_antes_y_se_rechaza_un_minuto_despues(): void
    {
        $this->travelToLocal('12:01');
        $this->assertTimeOver($this->register());
        $this->assertNull($this->entryOf($this->secondStudentId));

        $this->travelToLocal('11:59');
        $this->register()->assertCreated();
    }

    public function test_autorizar_se_permite_un_minuto_antes_y_se_rechaza_un_minuto_despues(): void
    {
        $this->travelToLocal('11:50');
        $requestId = (int) $this->request()->assertCreated()->json('data.id_solicitud');

        $this->travelToLocal('12:01');
        $this->assertTimeOver($this->authorize($requestId));
        $this->assertSame(LateEntryRequest::PENDIENTE, LateEntryRequest::query()->findOrFail($requestId)->estado);

        $this->travelToLocal('11:59');
        $this->authorize($requestId)->assertOk();
    }

    public function test_el_examen_sigue_en_curso_despues_del_fin_programado(): void
    {
        $this->travelToLocal('12:01');
        $this->assertTimeOver($this->register());

        $this->assertSame(Exam::EN_CURSO, Exam::query()->findOrFail($this->exam->id_examen)->estado);
    }
}
