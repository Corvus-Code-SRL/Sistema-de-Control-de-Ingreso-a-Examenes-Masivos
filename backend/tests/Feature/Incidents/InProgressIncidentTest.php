<?php

namespace Tests\Feature\Incidents;

use App\Exceptions\Exams\ExamStateException;
use App\Models\Exam;
use App\Models\User;
use App\Services\Exams\ExamExpulsionService;
use App\Services\Incidents\IncidentReviewService;
use App\Services\Security\AuditLogService;
use Carbon\Carbon;
use Database\Seeders\IncidentTypeSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Concerns\SeedsExamSituations;
use Tests\TestCase;

class InProgressIncidentTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsExamSituations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedExamSituations();
        $this->seed(IncidentTypeSeeder::class);
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function url(): string
    {
        return str_replace('/situaciones', '/incidencias', $this->situationUrl());
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'id_tipo_falta' => DB::table('tipo_falta')->where('nombre', 'Material no permitido')->value('id_falta'),
            'descripcion' => '  Usó un celular durante el examen sin autorización.  ',
            'id_ambiente' => $this->aulaId,
            'retirar' => false,
        ], $overrides);
    }

    private function attendance(): object
    {
        return DB::table('examen_estudiante')->where('id_examen', $this->situationExam->id_examen)
            ->where('id_estudiante', $this->situationStudent->id_estudiante)->first();
    }

    public function testPresentReportIsPendingWithoutAttemptAndDoesNotChangeAttendance(): void
    {
        $before = (array) $this->attendance();
        $response = $this->postJson($this->url(), $this->payload())->assertCreated()
            ->assertJsonPath('mensaje', 'Enviado a revisión del docente')
            ->assertJsonPath('data.estado', 'PENDIENTE')->assertJsonPath('data.id_intento', null)
            ->assertJsonPath('data.origen', 'EN_CURSO')->assertJsonPath('data.retiro_examen', false);
        $this->assertSame($before, (array) $this->attendance());
        $this->assertDatabaseHas('reporte_estudiante', [
            'id_reporte_est' => $response->json('data.id_reporte_est'),
            'id_usuario_reportante' => $this->docenteId,
            'id_ambiente_hecho' => $this->aulaId,
            'descripcion' => 'Usó un celular durante el examen sin autorización.',
            'evidencia_url' => null, 'evidencia_public_id' => null,
        ]);
        $this->assertSame(0, DB::table('central_riesgo')->count());
        $this->assertSame(0, DB::table('situacion_examen')->count());
        $this->assertSame(0, DB::table('intento_ingreso')->count());
    }

    public function testSeveralIncidentsAreAllowedAndGroupedInTheLiveView(): void
    {
        $first = $this->postJson($this->url(), $this->payload())->assertCreated();
        $second = $this->postJson($this->url(), $this->payload(['descripcion' => 'Después copió de otra hoja.']))
            ->assertCreated();
        $this->assertNotSame($first->json('data.id_reporte_est'), $second->json('data.id_reporte_est'));
        $response = $this->getJson("/api/examenes/{$this->situationExam->id_examen}/en-curso")
            ->assertOk()->assertJsonPath('data.totales.incidencias_pendientes', 2);
        $student = collect($response->json('data.estudiantes'))->firstWhere(
            'id_estudiante',
            $this->situationStudent->id_estudiante
        );
        $this->assertSame(2, $student['total_incidencias']);
    }

    public function testPrivatePngEvidenceUsesBothRelativePathColumns(): void
    {
        $response = $this->postJson($this->url(), $this->payload([
            'evidencia' => UploadedFile::fake()->image('documento.png'),
        ]))->assertCreated();
        $row = DB::table('reporte_estudiante')
            ->where('id_reporte_est', $response->json('data.id_reporte_est'))->first();
        $this->assertStringStartsWith('evidencias/reportes/', $row->evidencia_public_id);
        $this->assertSame($row->evidencia_public_id, $row->evidencia_url);
        Storage::disk('local')->assertExists($row->evidencia_public_id);
    }

    public function testInvalidEvidenceUsesTheAcceptanceMessage(): void
    {
        $this->postJson($this->url(), $this->payload([
            'evidencia' => UploadedFile::fake()->create('archivo.txt', 1, 'text/plain'),
        ]))->assertStatus(422)->assertJsonPath(
            'errors.evidencia.0',
            'Formato o tamaño no permitido. Use jpg, png o pdf de hasta 5 MB.'
        );
        $this->assertSame(0, DB::table('reporte_estudiante')->count());
    }

    public function testOversizeEvidenceIsRejected(): void
    {
        $this->postJson($this->url(), $this->payload([
            'evidencia' => UploadedFile::fake()->image('grande.png')->size(5121),
        ]))->assertStatus(422)->assertJsonValidationErrors('evidencia');
    }

    /** @dataProvider invalidInputs */
    public function testRejectsInvalidAndProtectedInputs(string $field, $value): void
    {
        $this->postJson($this->url(), $this->payload([$field => $value]))
            ->assertStatus(422)->assertJsonValidationErrors($field);
        $this->assertSame(0, DB::table('reporte_estudiante')->count());
    }

    public static function invalidInputs(): array
    {
        return [
            ['descripcion', 'corto'], ['descripcion', str_repeat('a', 501)], ['id_tipo_falta', 2147483647],
            ['id_intento', 1], ['id_estudiante', 1], ['estado', 'APROBADO'], ['retirar', 'sí'],
            ['origen', 'CONTROL_INGRESO'], ['expulsado_en', '2026-10-30'], ['id_ambiente', 0],
        ];
    }

    public function testOnlyTheOwnerAndAssignedAssistantMayReport(): void
    {
        $this->actingAs(User::query()->findOrFail($this->otroDocenteId));
        $this->postJson($this->url(), $this->payload())->assertForbidden();
        $this->actAsSituationAssistant($this->aulaId);
        $this->postJson($this->url(), $this->payload())->assertCreated();
        $this->assertDatabaseHas('reporte_estudiante', ['id_usuario_reportante' => $this->otroDocenteId]);
    }

    public function testAssistantCannotReportFromAnotherRoom(): void
    {
        $this->actAsSituationAssistant($this->otraAulaId);
        $this->postJson($this->url(), $this->payload())->assertForbidden();
    }

    public function testGuestCannotReport(): void
    {
        $this->actAsGuest();
        $this->postJson($this->url(), $this->payload())->assertUnauthorized();
    }

    public function testChangedRoomRequiresFreshContext(): void
    {
        $this->postJson($this->situationUrl(), $this->situationPayload())->assertCreated();
        $this->postJson($this->url(), $this->payload())->assertStatus(409);
        $this->postJson($this->url(), $this->payload(['id_ambiente' => $this->otraAulaId]))->assertCreated();
    }

    public function testAbsentOrMissingAttendanceDoesNotGetInvented(): void
    {
        DB::table('examen_estudiante')->where('id_examen', $this->situationExam->id_examen)->update([
            'estado_ingreso' => 'NO_INGRESO', 'hora_ingreso' => null,
            'id_usuario_controlador' => null, 'registrado_en' => null,
        ]);
        $this->postJson($this->url(), $this->payload())->assertStatus(409);
        DB::table('examen_estudiante')->where('id_examen', $this->situationExam->id_examen)->delete();
        $this->postJson($this->url(), $this->payload())->assertStatus(409);
        $this->assertSame(0, DB::table('reporte_estudiante')->count());
    }

    /** @dataProvider closedStates */
    public function testOtherExamStatesRejectWrites(string $state): void
    {
        $this->situationExam->update(['estado' => $state]);
        $this->postJson($this->url(), $this->payload())->assertStatus(409);
    }

    public static function closedStates(): array
    {
        return [[Exam::PROGRAMADO], [Exam::EN_INGRESO], [Exam::FINALIZADO]];
    }

    public function testExpulsionPreservesIngressAndRemovesStudentFromPresentTotals(): void
    {
        $this->createSituationFixture();
        $before = (array) $this->attendance();
        $this->postJson($this->url(), $this->payload(['retirar' => true]))->assertCreated()
            ->assertJsonPath('data.retiro_examen', true);
        $after = (array) $this->attendance();
        $this->assertNotNull($after['expulsado_en']);
        $this->assertSame(array_replace($before, ['expulsado_en' => $after['expulsado_en']]), $after);
        $this->getJson("/api/examenes/{$this->situationExam->id_examen}/en-curso")->assertOk()
            ->assertJsonPath('data.totales.presentes', 0)->assertJsonPath('data.totales.expulsados', 1)
            ->assertJsonPath('data.totales.ausentes', 1)->assertJsonPath('data.totales.total', 2);
        $this->assertSame(1, DB::table('situacion_examen')->count());
        $this->postJson($this->situationUrl(), $this->situationPayload())->assertStatus(409);
        $this->postJson($this->url(), $this->payload())->assertStatus(409);
        $this->assertDatabaseHas('log', [
            'tabla_afectada' => 'examen_estudiante', 'id_usuario' => $this->docenteId,
            'id_accion' => DB::table('accion')->where('operacion', 'EXPULSAR_ESTUDIANTE')->value('id_accion'),
        ]);
    }

    public function testLateIngressIsPreservedWhenExpelled(): void
    {
        DB::table('examen_estudiante')->where('id_examen', $this->situationExam->id_examen)
            ->update(['estado_ingreso' => 'CON_RETRASO']);
        $this->postJson($this->url(), $this->payload(['retirar' => true]))->assertCreated();
        $this->assertSame('CON_RETRASO', $this->attendance()->estado_ingreso);
        $this->getJson("/api/examenes/{$this->situationExam->id_examen}/en-curso")->assertOk()
            ->assertJsonPath('data.totales.con_retraso', 0)->assertJsonPath('data.totales.expulsados', 1);
    }

    public function testSharedIngressGuardUsesCampusTime(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-30T14:40:00Z'));
        $this->postJson($this->url(), $this->payload(['retirar' => true]))->assertCreated();
        $this->expectException(ExamStateException::class);
        $this->expectExceptionMessage('El estudiante fue expulsado de este examen a las 10:40');
        app(ExamExpulsionService::class)->assertCanEnter(
            $this->situationExam->id_examen,
            $this->situationStudent->id_estudiante
        );
    }

    public function testReviewUsesOriginalRoomAndApprovalAloneCreatesRiskEntry(): void
    {
        $id = $this->postJson($this->url(), $this->payload())->assertCreated()->json('data.id_reporte_est');
        $this->postJson($this->situationUrl(), $this->situationPayload())->assertCreated();
        $review = app(IncidentReviewService::class);
        $detail = $review->find($this->docenteId, "estudiante-{$id}");
        $this->assertSame('EN_CURSO', $detail['origen']);
        $this->assertNull($detail['intento']);
        $this->assertSame($this->aulaId, (int) $detail['ambiente']['id_ambiente']);
        $this->assertSame(0, DB::table('central_riesgo')->count());
        $review->approve($this->docenteId, "estudiante-{$id}", null);
        $this->assertDatabaseHas('central_riesgo', ['id_reporte_est' => $id]);
    }

    public function testRejectingReportDoesNotSilentlyUndoRecordedExpulsion(): void
    {
        $id = $this->postJson($this->url(), $this->payload(['retirar' => true]))
            ->assertCreated()->json('data.id_reporte_est');
        app(IncidentReviewService::class)->reject($this->docenteId, "estudiante-{$id}", 'No se confirmó la falta.');
        $this->assertSame(0, DB::table('central_riesgo')->count());
        $this->assertNotNull($this->attendance()->expulsado_en);
    }

    public function testAuditFailureRollsBackReportExpulsionAndPrivateFile(): void
    {
        $before = (array) $this->attendance();
        $this->mock(AuditLogService::class, function ($mock): void {
            $mock->shouldReceive('registrar')->once()->andThrow(new RuntimeException('Auditoría no disponible'));
        });
        $this->withoutExceptionHandling();
        try {
            $this->postJson($this->url(), $this->payload([
                'retirar' => true, 'evidencia' => UploadedFile::fake()->image('prueba.png'),
            ]));
            $this->fail('La auditoría debía impedir confirmar la operación.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Auditoría no disponible', $exception->getMessage());
        }
        $this->assertSame($before, (array) $this->attendance());
        $this->assertSame(0, DB::table('reporte_estudiante')->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }
    /** @dataProvider invalidDatabaseCases */
    public function testDatabaseRejectsInconsistentOrigins(array $changes, string $code): void
    {
        $id = $this->postJson($this->url(), $this->payload())->assertCreated()->json('data.id_reporte_est');
        if (isset($changes['outside_room'])) {
            $changes = ['id_ambiente_hecho' => $this->aulaInactivaId];
        }
        try {
            DB::transaction(function () use ($id, $changes): void {
                DB::table('reporte_estudiante')->where('id_reporte_est', $id)->update($changes);
            });
            $this->fail('La restricción de la base debía rechazar la escritura.');
        } catch (QueryException $exception) {
            $this->assertSame($code, (string) $exception->getCode());
        }
        $this->assertDatabaseHas('reporte_estudiante', [
            'id_reporte_est' => $id, 'origen' => 'EN_CURSO', 'id_ambiente_hecho' => $this->aulaId,
        ]);
    }

    public static function invalidDatabaseCases(): array
    {
        return [
            [['origen' => 'INVENTADO'], '23514'],
            [['id_ambiente_hecho' => null], '23514'],
            [['origen' => 'LEGADO', 'retiro_examen' => true], '23514'],
            [['outside_room' => true], '23503'],
        ];
    }
}
