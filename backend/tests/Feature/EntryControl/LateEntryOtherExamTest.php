<?php

namespace Tests\Feature\EntryControl;

use App\Exceptions\EntryControl\LateEntryException;
use App\Models\AuditLog;
use App\Models\Exam;
use App\Models\LateEntryRequest;
use App\Models\StudentExam;
use App\Services\EntryControl\LateEntryService;
use App\Services\EntryControl\VerificationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\SeedsLateEntryExam;
use Tests\TestCase;

/**
 * HU-037: si el estudiante ya ingresó a otro examen abierto (EN_INGRESO o EN_CURSO) cuyo fin
 * programado no pasó, se advierte y quien controla decide: sin confirmación responde 409 con la
 * advertencia; con confirmación continúa y la bitácora lo registra.
 */
class LateEntryOtherExamTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsLateEntryExam;

    private Exam $otherExam;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedLateEntryExam();
        $this->otherExam = $this->createOtherExam(['nombre_examen' => 'Parcial de Física']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function createOtherExam(array $overrides): Exam
    {
        $exam = $this->createExam(array_merge(['estado' => Exam::EN_CURSO], $overrides), [$this->aulaId]);
        DB::table('grupo_examen')->insert(['id_examen' => $exam->id_examen, 'id_grupo' => $this->grupoPropioId]);

        return $exam;
    }

    private function enterOtherExam(string $state = StudentExam::INGRESO, ?int $studentId = null): void
    {
        DB::table('examen_estudiante')->insert([
            'id_examen' => $this->otherExam->id_examen,
            'id_estudiante' => $studentId ?? $this->secondStudentId,
            'id_grupo' => $this->grupoPropioId,
            'estado_habilitacion' => StudentExam::HABILITADO,
            'estado_ingreso' => $state,
            'hora_ingreso' => $state === StudentExam::NO_INGRESO ? null : '08:05:00',
            'id_ambiente' => $state === StudentExam::NO_INGRESO ? null : $this->aulaId,
        ]);
    }

    private function verify(): TestResponse
    {
        return $this->postJson($this->lateEntryUrl('verificar'), [
            'cod_sis' => $this->studentColumn($this->secondStudentId, 'cod_sis'),
        ]);
    }

    private function register(bool $confirm = false): TestResponse
    {
        return $this->postJson($this->lateEntryUrl('registrar'), [
            'id_estudiante' => $this->secondStudentId,
            'id_ambiente' => $this->otraAulaId,
        ] + ($confirm ? ['confirmar_otro_examen' => true] : []));
    }

    private function request(bool $confirm = false): TestResponse
    {
        return $this->postJson($this->lateEntryUrl('solicitudes'), [
            'id_estudiante' => $this->secondStudentId,
            'id_ambiente' => $this->otraAulaId,
        ] + ($confirm ? ['confirmar_otro_examen' => true] : []));
    }

    private function authorize(int $requestId, bool $confirm = false): TestResponse
    {
        return $this->postJson(
            $this->lateEntryUrl("solicitudes/{$requestId}/autorizar"),
            $confirm ? ['confirmar_otro_examen' => true] : []
        );
    }

    /** Pone ambos exámenes en el mismo día a las horas indicadas. */
    private function schedule(Exam $exam, string $start, int $minutes): void
    {
        $startsAt = Carbon::parse("2026-10-31 {$start}");
        DB::table('examen')->where('id_examen', $exam->id_examen)->update([
            'fecha' => $startsAt->toDateString(),
            'hora_inicio' => $start,
            'hora_fin' => $startsAt->copy()->addMinutes($minutes)->format('H:i'),
            'duracion' => $minutes,
        ]);
    }

    private function expectedWarning(): array
    {
        return [
            'id_examen' => (int) $this->otherExam->id_examen,
            'nombre_examen' => 'Parcial de Física',
            'materia' => 'Calculo II',
            'docente' => 'Ana Rojas',
            'hora_ingreso' => '08:05',
            'nro_aula' => '691A',
        ];
    }

    private function lastAudit(string $action): array
    {
        return AuditLog::query()
            ->join('accion', 'accion.id_accion', '=', 'log.id_accion')
            ->where('accion.operacion', $action)
            ->latest('log.id_log')
            ->firstOrFail(['log.*'])
            ->nuevo_valor;
    }

    public function test_la_verificacion_autoriza_y_advierte_con_examen_materia_docente_hora_y_aula(): void
    {
        $this->enterOtherExam();
        $this->actAsTeacher($this->docenteId);

        $this->verify()
            ->assertOk()
            ->assertJsonPath('data.veredicto', VerificationService::AUTHORIZED)
            ->assertJsonPath('data.autorizado', true)
            ->assertJsonPath('data.otro_examen', $this->expectedWarning());
    }

    public function test_sin_otro_examen_la_advertencia_es_null(): void
    {
        $this->actAsTeacher($this->docenteId);

        $this->verify()->assertOk()->assertJsonPath('data.otro_examen', null);
    }

    public function test_registrar_sin_confirmar_responde_409_y_con_confirmacion_registra(): void
    {
        $this->enterOtherExam(StudentExam::CON_RETRASO);
        $this->actAsTeacher($this->docenteId);

        $this->register()
            ->assertStatus(409)
            ->assertJsonPath('veredicto', LateEntryException::OTHER_EXAM)
            ->assertJsonPath('message', LateEntryException::OTHER_EXAM_MESSAGE)
            ->assertJsonPath('otro_examen', $this->expectedWarning());
        $this->assertNull($this->entryOf($this->secondStudentId));

        $this->register(true)->assertCreated();
        $this->assertSame(StudentExam::CON_RETRASO, $this->entryOf($this->secondStudentId)->estado_ingreso);

        $audit = $this->lastAudit(LateEntryService::REGISTER_ACTION);
        $this->assertTrue($audit['confirmado_pese_a_otro_examen']);
        $this->assertEquals([
            'id_examen' => (int) $this->otherExam->id_examen,
            'nombre_examen' => 'Parcial de Física',
            'hora_ingreso' => '08:05',
            'nro_aula' => '691A',
        ], $audit['otro_examen']);
    }

    public function test_solicitar_sin_confirmar_responde_409_y_con_confirmacion_solicita(): void
    {
        $this->enterOtherExam();
        $this->actAsUserId($this->assistantBId);

        $this->request()->assertStatus(409)->assertJsonPath('otro_examen.nombre_examen', 'Parcial de Física');
        $this->assertSame(0, LateEntryRequest::query()->count());

        $this->request(true)->assertCreated();
        $this->assertTrue($this->lastAudit(LateEntryService::REQUEST_ACTION)['confirmado_pese_a_otro_examen']);
    }

    public function test_autorizar_exige_confirmar_si_ingreso_a_otro_examen_mientras_esperaba(): void
    {
        $this->actAsUserId($this->assistantBId);
        $requestId = (int) $this->request()->assertCreated()->json('data.id_solicitud');
        $this->enterOtherExam();

        $this->actAsTeacher($this->docenteId);
        $this->authorize($requestId)
            ->assertStatus(409)
            ->assertJsonPath('veredicto', LateEntryException::OTHER_EXAM);
        $this->assertNull($this->entryOf($this->secondStudentId));
        $this->assertSame(LateEntryRequest::PENDIENTE, LateEntryRequest::query()->findOrFail($requestId)->estado);

        $this->authorize($requestId, true)->assertOk();
        $this->assertSame(StudentExam::CON_RETRASO, $this->entryOf($this->secondStudentId)->estado_ingreso);
        $this->assertTrue($this->lastAudit(LateEntryService::AUTHORIZE_ACTION)['confirmado_pese_a_otro_examen']);
    }

    public function test_sin_conflicto_la_bitacora_no_lleva_la_advertencia(): void
    {
        $this->actAsTeacher($this->docenteId);
        $this->register()->assertCreated();

        $audit = $this->lastAudit(LateEntryService::REGISTER_ACTION);
        $this->assertArrayNotHasKey('otro_examen', $audit);
        $this->assertArrayNotHasKey('confirmado_pese_a_otro_examen', $audit);
    }

    private function countListQueries(): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $rows = collect($this->getJson($this->lateEntryUrl('solicitudes'))->assertOk()->json('data'));
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return [$rows, $queries];
    }

    public function test_la_lista_de_solicitudes_advierte_en_las_pendientes_sin_consultas_por_fila(): void
    {
        $this->actAsUserId($this->assistantBId);
        $this->request()->assertCreated();
        $this->enterOtherExam();
        $this->actAsTeacher($this->docenteId);
        [$rows, $withOneRequest] = $this->countListQueries();

        $this->assertEquals(
            $this->expectedWarning(),
            $rows->firstWhere('estudiante.id_estudiante', $this->secondStudentId)['otro_examen']
        );

        $this->actAsUserId($this->assistantAId);
        $this->postJson($this->lateEntryUrl('solicitudes'), [
            'id_estudiante' => $this->firstStudentId,
            'id_ambiente' => $this->aulaId,
        ])->assertCreated();
        $this->actAsTeacher($this->docenteId);
        [$rows, $withTwoRequests] = $this->countListQueries();

        $this->assertSame($withOneRequest, $withTwoRequests);
        $this->assertNull($rows->firstWhere('estudiante.id_estudiante', $this->firstStudentId)['otro_examen']);

        $this->enterOtherExam(StudentExam::INGRESO, $this->firstStudentId);
        [$rows, $withTwoWarnings] = $this->countListQueries();

        $this->assertSame($withOneRequest, $withTwoWarnings);
        $this->assertSame(
            'Parcial de Física',
            $rows->firstWhere('estudiante.id_estudiante', $this->firstStudentId)['otro_examen']['nombre_examen']
        );
    }

    public function test_examenes_seguidos_no_se_advierten_aunque_el_anterior_siga_en_curso(): void
    {
        // P1 08:00–10:00 sigue EN_CURSO hasta su cierre automático; P2 10:00–12:00, retraso a las 10:05.
        $this->schedule($this->otherExam, '08:00', 120);
        $this->schedule($this->exam, '10:00', 120);
        $this->enterOtherExam();
        $this->travelToLocal('10:05');
        $this->actAsTeacher($this->docenteId);

        $this->verify()->assertOk()->assertJsonPath('data.otro_examen', null);
        $this->register()->assertCreated();
    }

    public function test_examenes_solapados_el_mismo_dia_se_advierten(): void
    {
        $this->schedule($this->otherExam, '09:00', 120);
        $this->schedule($this->exam, '10:00', 120);
        $this->enterOtherExam();
        $this->travelToLocal('10:05');
        $this->actAsTeacher($this->docenteId);

        $this->verify()->assertOk()->assertJsonPath('data.otro_examen.nombre_examen', 'Parcial de Física');
    }

    public function test_un_examen_finalizado_o_un_ausente_no_se_advierten(): void
    {
        $this->enterOtherExam(StudentExam::NO_INGRESO);
        $this->actAsTeacher($this->docenteId);
        $this->verify()->assertOk()->assertJsonPath('data.otro_examen', null);

        DB::table('examen_estudiante')->where('id_examen', $this->otherExam->id_examen)->update([
            'estado_ingreso' => StudentExam::INGRESO, 'hora_ingreso' => '08:05:00', 'id_ambiente' => $this->aulaId,
        ]);
        DB::table('examen')->where('id_examen', $this->otherExam->id_examen)->update(['estado' => Exam::FINALIZADO]);

        $this->verify()->assertOk()->assertJsonPath('data.otro_examen', null);
        $this->register()->assertCreated();
    }

    public function test_un_examen_en_ingreso_tambien_se_advierte(): void
    {
        $this->enterOtherExam();
        DB::table('examen')->where('id_examen', $this->otherExam->id_examen)->update(['estado' => Exam::EN_INGRESO]);
        $this->actAsTeacher($this->docenteId);

        $this->register()->assertStatus(409)->assertJsonPath('veredicto', LateEntryException::OTHER_EXAM);
    }

    public function test_advierte_con_el_mismo_docente_y_con_otro_docente(): void
    {
        $this->enterOtherExam();
        $this->actAsTeacher($this->docenteId);
        $this->verify()->assertJsonPath('data.otro_examen.docente', 'Ana Rojas');

        DB::table('examen')->where('id_examen', $this->otherExam->id_examen)
            ->update(['id_usuario_docente' => $this->otroDocenteId]);
        $this->verify()->assertJsonPath('data.otro_examen.docente', 'Luis Vargas');
    }

    public function test_reingresar_en_el_mismo_examen_sigue_siendo_duplicado_y_no_advierte(): void
    {
        $this->enterOtherExam();
        $this->insertEntry($this->secondStudentId, '10:01:00', $this->otraAulaId);
        $this->actAsTeacher($this->docenteId);

        $this->verify()
            ->assertOk()
            ->assertJsonPath('data.veredicto', VerificationService::DUPLICATE)
            ->assertJsonPath('data.otro_examen', null);
        $this->register(true)->assertStatus(409)->assertJsonPath('veredicto', VerificationService::DUPLICATE);
    }
}
