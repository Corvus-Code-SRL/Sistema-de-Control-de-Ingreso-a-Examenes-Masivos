<?php

namespace Tests\Feature\EntryControl;

use App\Exceptions\EntryControl\LateEntryException;
use App\Http\Controllers\Incidents\IncidentReportController;
use App\Models\Exam;
use App\Models\IncidentType;
use App\Models\LateEntryRequest;
use App\Services\EntryControl\EntryControlSnapshotService;
use App\Services\EntryControl\VerificationService;
use App\Services\Incidents\IncidentReportService;
use Carbon\Carbon;
use Database\Seeders\IncidentTypeSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\SeedsLateEntryExam;
use Tests\TestCase;

/**
 * HU-037 con el criterio 15 de HU-032: en el ingreso con retraso, un resultado negativo o un
 * rechazo manual deja un intento que se puede reportar como incidencia, con las mismas reglas,
 * el mismo origen (CONTROL_INGRESO) y la misma bitácora que el control de ingreso.
 */
class LateEntryIncidentReportTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsLateEntryExam;

    private const WRONG_CI = '0000001';
    private const DESCRIPTION = 'Presentó un carnet que no corresponde a la fotografía.';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedLateEntryExam();
        $this->seed(IncidentTypeSeeder::class);
        Storage::fake('local');
        Storage::fake('public');
        $this->actAsTeacher($this->docenteId);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @param array<string, string|int> $overrides */
    private function verify(int $studentId, int $roomId, array $overrides = []): TestResponse
    {
        return $this->postJson($this->lateEntryUrl('verificar'), array_merge([
            'cod_sis' => $this->studentColumn($studentId, 'cod_sis'),
            'id_ambiente' => $roomId,
        ], $overrides));
    }

    /** Intento del segundo estudiante (692B) con un CI que no coincide. */
    private function deniedAttempt(): int
    {
        return (int) $this->verify($this->secondStudentId, $this->otraAulaId, ['ci' => self::WRONG_CI])
            ->assertOk()
            ->json('data.id_intento');
    }

    /** @param array<string, string|int|UploadedFile> $overrides */
    private function report(int $attemptId, array $overrides = []): TestResponse
    {
        return $this->post($this->lateEntryUrl("intentos/{$attemptId}/reporte"), array_merge([
            'id_tipo_falta' => IncidentType::query()->firstOrFail()->id_falta,
            'descripcion' => self::DESCRIPTION,
        ], $overrides), ['Accept' => 'application/json']);
    }

    private function attemptsCount(): int
    {
        return DB::table('intento_ingreso')->where('id_examen', $this->exam->id_examen)->count();
    }

    private function auditCount(string $table): int
    {
        return DB::table('log')
            ->where('tabla_afectada', $table)
            ->where('id_accion', DB::table('accion')->where('operacion', 'CREAR')->value('id_accion'))
            ->count();
    }

    public function test_el_resultado_negativo_del_docente_registra_el_intento_en_el_ambiente_elegido(): void
    {
        $response = $this->verify($this->secondStudentId, $this->aulaId, ['ci' => self::WRONG_CI])
            ->assertOk()
            ->assertJsonPath('data.veredicto', VerificationService::CI_MISMATCH)
            ->assertJsonPath('data.reportado', false);

        $this->assertDatabaseHas('intento_ingreso', [
            'id_intento' => $response->json('data.id_intento'),
            'id_examen' => $this->exam->id_examen,
            'id_estudiante' => $this->secondStudentId,
            'id_ambiente' => $this->aulaId,
            'id_usuario_controlador' => $this->docenteId,
            'motivo' => VerificationService::CI_MISMATCH,
            'ci_presentado' => self::WRONG_CI,
        ]);
        $this->assertNotNull($response->json('data.registrado_en'));
        $this->assertSame(1, $this->auditCount('intento_ingreso'));
    }

    public function test_el_resultado_negativo_del_auxiliar_queda_en_su_ambiente(): void
    {
        $this->actAsUserId($this->assistantBId);

        $response = $this->verify($this->firstStudentId, $this->otraAulaId)
            ->assertOk()
            ->assertJsonPath('data.veredicto', VerificationService::WRONG_ROOM);

        $this->assertDatabaseHas('intento_ingreso', [
            'id_intento' => $response->json('data.id_intento'),
            'id_ambiente' => $this->otraAulaId,
            'id_usuario_controlador' => $this->assistantBId,
        ]);
    }

    public function test_no_encontrado_ya_ingreso_y_expulsado_tambien_registran_el_intento(): void
    {
        $this->postJson($this->lateEntryUrl('verificar'), ['cod_sis' => '999999999', 'id_ambiente' => $this->aulaId])
            ->assertOk()
            ->assertJsonPath('data.veredicto', VerificationService::NOT_FOUND);
        $this->assertDatabaseHas('intento_ingreso', [
            'id_examen' => $this->exam->id_examen,
            'id_estudiante' => null,
            'cod_sis' => '999999999',
            'motivo' => VerificationService::NOT_FOUND,
        ]);

        $this->insertEntry($this->firstStudentId, '10:01:00', $this->aulaId);
        $this->verify($this->firstStudentId, $this->aulaId)
            ->assertOk()
            ->assertJsonPath('data.veredicto', VerificationService::DUPLICATE);

        $this->insertEntry($this->secondStudentId, '10:02:00', $this->otraAulaId);
        DB::table('examen_estudiante')->where('id_examen', $this->exam->id_examen)
            ->where('id_estudiante', $this->secondStudentId)
            ->update(['expulsado_en' => Carbon::parse('2026-10-30T14:40:00Z')]);
        $this->verify($this->secondStudentId, $this->otraAulaId)
            ->assertOk()
            ->assertJsonPath('data.veredicto', VerificationService::DUPLICATE);

        $this->assertSame(3, $this->attemptsCount());
    }

    public function test_el_resultado_autorizado_no_registra_nada(): void
    {
        $this->verify($this->secondStudentId, $this->otraAulaId)
            ->assertOk()
            ->assertJsonPath('data.autorizado', true)
            ->assertJsonPath('data.id_intento', null);

        $this->assertSame(0, $this->attemptsCount());
    }

    public function test_sin_ambiente_de_control_responde_422_y_no_escribe(): void
    {
        $this->postJson($this->lateEntryUrl('verificar'), [
            'cod_sis' => $this->studentColumn($this->secondStudentId, 'cod_sis'),
            'ci' => self::WRONG_CI,
        ])->assertStatus(422)->assertJsonValidationErrors(['id_ambiente']);

        $this->assertSame(0, $this->attemptsCount());
    }

    public function test_el_rechazo_manual_registra_decision_del_controlador(): void
    {
        $response = $this->postJson($this->lateEntryUrl('rechazar'), [
            'id_estudiante' => $this->secondStudentId,
            'id_ambiente' => $this->otraAulaId,
            'motivo' => 'DECISION_CONTROLADOR',
        ])->assertCreated()
            ->assertJsonPath('data.registrado', true)
            ->assertJsonPath('data.motivo', 'DECISION_CONTROLADOR');

        $this->assertDatabaseHas('intento_ingreso', [
            'id_intento' => $response->json('data.id_intento'),
            'id_estudiante' => $this->secondStudentId,
            'id_ambiente' => $this->otraAulaId,
            'motivo' => 'DECISION_CONTROLADOR',
        ]);
        $this->assertNull($this->entryOf($this->secondStudentId));
    }

    public function test_el_rechazo_manual_exige_un_resultado_que_autorice_y_un_examen_en_curso(): void
    {
        $this->postJson($this->lateEntryUrl('rechazar'), [
            'id_estudiante' => $this->secondStudentId,
            'id_ambiente' => $this->otraAulaId,
            'ci' => self::WRONG_CI,
            'motivo' => 'DECISION_CONTROLADOR',
        ])->assertStatus(422)->assertJsonPath('veredicto', VerificationService::CI_MISMATCH);
        $this->postJson($this->lateEntryUrl('rechazar'), [
            'id_estudiante' => $this->secondStudentId,
            'id_ambiente' => $this->otraAulaId,
            'motivo' => 'NO_EXISTE',
        ])->assertStatus(422)->assertJsonValidationErrors(['motivo']);

        $this->setLateEntryExamState(Exam::FINALIZADO);
        $this->postJson($this->lateEntryUrl('rechazar'), [
            'id_estudiante' => $this->secondStudentId,
            'id_ambiente' => $this->otraAulaId,
            'motivo' => 'DECISION_CONTROLADOR',
        ])->assertStatus(409)->assertJsonPath('veredicto', LateEntryException::EXAM_NOT_IN_PROGRESS);

        $this->assertSame(0, $this->attemptsCount());
    }

    public function test_reporta_el_intento_con_origen_control_ingreso_y_bitacora(): void
    {
        $attemptId = $this->deniedAttempt();

        $response = $this->report($attemptId)
            ->assertCreated()
            ->assertJsonPath('mensaje', IncidentReportController::REPORTED_MESSAGE)
            ->assertJsonPath('data.estado', 'PENDIENTE')
            ->assertJsonPath('data.origen', 'CONTROL_INGRESO')
            ->assertJsonPath('data.id_intento', $attemptId)
            ->assertJsonPath('data.retiro_examen', false);

        $this->assertDatabaseHas('reporte_estudiante', [
            'id_reporte_est' => $response->json('data.id_reporte_est'),
            'id_examen' => $this->exam->id_examen,
            'id_estudiante' => $this->secondStudentId,
            'id_intento' => $attemptId,
            'id_usuario_reportante' => $this->docenteId,
            'descripcion' => self::DESCRIPTION,
            'origen' => 'CONTROL_INGRESO',
            'estado' => 'PENDIENTE',
        ]);
        $this->assertSame(1, $this->auditCount('reporte_estudiante'));
    }

    public function test_el_auxiliar_reporta_con_evidencia_solo_en_su_ambiente(): void
    {
        $this->actAsUserId($this->assistantBId);
        $ownAttempt = (int) $this->verify($this->firstStudentId, $this->otraAulaId)->json('data.id_intento');
        $this->actAsUserId($this->assistantAId);
        $foreignAttempt = (int) $this->verify($this->secondStudentId, $this->aulaId)->json('data.id_intento');

        $this->actAsUserId($this->assistantBId);
        $this->report($foreignAttempt)
            ->assertForbidden()
            ->assertJsonPath('message', EntryControlSnapshotService::FOREIGN_ROOM_MESSAGE);
        $response = $this->report($ownAttempt, [
            'evidencia' => UploadedFile::fake()->create('carnet.pdf', 200, 'application/pdf'),
        ])->assertCreated();

        $this->assertNotNull(DB::table('reporte_estudiante')
            ->where('id_reporte_est', $response->json('data.id_reporte_est'))
            ->value('evidencia_url'));
    }

    public function test_un_intento_ya_reportado_responde_422_en_id_intento(): void
    {
        $attemptId = $this->deniedAttempt();
        $this->report($attemptId)->assertCreated();

        $this->report($attemptId)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['id_intento']);
        $this->assertSame(1, DB::table('reporte_estudiante')->where('id_intento', $attemptId)->count());
    }

    public function test_valida_tipo_descripcion_y_evidencia_como_hu032(): void
    {
        $attemptId = $this->deniedAttempt();

        $this->report($attemptId, [
            'id_tipo_falta' => 999999,
            'descripcion' => 'corta',
            'evidencia' => UploadedFile::fake()->create('nota.txt', 10, 'text/plain'),
        ])->assertStatus(422)->assertJsonValidationErrors(['id_tipo_falta', 'descripcion', 'evidencia']);
        $this->report($attemptId, ['evidencia' => UploadedFile::fake()->create('grande.pdf', 6000, 'application/pdf')])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['evidencia']);

        $this->assertSame(0, DB::table('reporte_estudiante')->count());
    }

    public function test_solo_se_reporta_con_el_examen_en_curso(): void
    {
        $attemptId = $this->deniedAttempt();

        foreach ([Exam::EN_INGRESO, Exam::FINALIZADO] as $state) {
            $this->setLateEntryExamState($state);
            $this->report($attemptId)
                ->assertStatus(409)
                ->assertJsonPath('message', IncidentReportService::LATE_ENTRY_NOT_IN_PROGRESS_MESSAGE);
        }

        $this->assertSame(0, DB::table('reporte_estudiante')->count());
    }

    public function test_se_reporta_despues_del_fin_programado_si_el_examen_sigue_en_curso(): void
    {
        DB::table('examen')->where('id_examen', $this->exam->id_examen)->update([
            'fecha' => '2026-10-31',
            'hora_inicio' => '10:00',
            'hora_fin' => '12:00',
            'duracion' => 120,
        ]);
        $this->travelToLocal('11:30');
        $attemptId = $this->deniedAttempt();

        $this->travelToLocal('12:30');
        $this->report($attemptId)->assertCreated();
    }

    public function test_no_reporta_intentos_de_otro_examen_ni_ids_invalidos_ni_sin_sesion(): void
    {
        $attemptId = $this->deniedAttempt();
        $other = $this->createExam(['estado' => Exam::EN_CURSO], [$this->aulaId]);

        $this->post(
            $this->lateEntryUrl("intentos/{$attemptId}/reporte", $other),
            ['id_tipo_falta' => IncidentType::query()->firstOrFail()->id_falta, 'descripcion' => self::DESCRIPTION],
            ['Accept' => 'application/json']
        )->assertNotFound();
        $this->post($this->lateEntryUrl('intentos/abc/reporte'), [], ['Accept' => 'application/json'])
            ->assertNotFound();
        $this->post($this->lateEntryUrl('intentos/1234567890/reporte'), [], ['Accept' => 'application/json'])
            ->assertNotFound();

        $this->actAsGuest();
        $this->report($attemptId)->assertUnauthorized();
    }

    public function test_reportar_no_cambia_la_solicitud_pendiente_ni_el_ingreso(): void
    {
        $this->actAsUserId($this->assistantBId);
        $request = $this->postJson($this->lateEntryUrl('solicitudes'), [
            'id_estudiante' => $this->secondStudentId,
            'id_ambiente' => $this->otraAulaId,
        ])->assertCreated();
        $attemptId = (int) $this->verify($this->secondStudentId, $this->otraAulaId, ['ci' => self::WRONG_CI])
            ->json('data.id_intento');

        $this->report($attemptId)->assertCreated();

        $this->assertDatabaseHas('solicitud_ingreso_retraso', [
            'id_solicitud' => $request->json('data.id_solicitud'),
            'estado' => LateEntryRequest::PENDIENTE,
        ]);
        $this->assertNull($this->entryOf($this->secondStudentId));
    }
}
