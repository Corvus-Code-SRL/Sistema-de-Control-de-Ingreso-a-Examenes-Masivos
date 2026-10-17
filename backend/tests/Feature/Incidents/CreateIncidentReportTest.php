<?php

namespace Tests\Feature\Incidents;

use App\Models\Exam;
use App\Models\IncidentType;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentReport;
use App\Models\User;
use App\Services\Security\AuditLogService;
use Database\Seeders\IncidentTypeSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Concerns\SeedsExamCatalog;
use Tests\Concerns\SeedsRiskCenterRecords;
use Tests\TestCase;

class CreateIncidentReportTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsExamCatalog;
    use SeedsRiskCenterRecords;

    private Exam $exam;
    private array $context;
    private int $attemptId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedExamCatalog();
        $this->seed(IncidentTypeSeeder::class);
        Storage::fake('local');
        Storage::fake('public');
        $this->exam = $this->createExam(['estado' => Exam::EN_INGRESO], [$this->aulaId, $this->otraAulaId]);
        $this->context = [
            'id_usuario' => $this->docenteId,
            'id_examen' => $this->exam->id_examen,
            'id_ambiente' => $this->aulaId,
            'id_estudiante' => Student::query()->firstOrFail()->id_estudiante,
            'id_tipo_falta' => IncidentType::query()->firstOrFail()->id_falta,
        ];
        $this->attemptId = $this->insertEntryAttempt($this->context);
        $this->actingAs(User::query()->findOrFail($this->docenteId));
    }

    private function url(?int $attemptId = null): string
    {
        $id = $attemptId ?? $this->attemptId;

        return "/api/control-ingreso/examenes/{$this->exam->id_examen}/intentos/{$id}/reporte";
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'id_tipo_falta' => $this->context['id_tipo_falta'],
            'descripcion' => 'La fotografía no corresponde al estudiante.',
        ], $overrides);
    }

    public function testCreatesPendingReportWithoutEvidenceAndAuditsRealActor(): void
    {
        $response = $this->postJson($this->url(), $this->payload(['descripcion' => '  Documento no válido.  ']))
            ->assertCreated()
            ->assertJsonPath('data.estado', 'PENDIENTE')
            ->assertJsonPath('data.id_intento', $this->attemptId);

        $this->assertDatabaseHas('reporte_estudiante', [
            'id_reporte_est' => $response->json('data.id_reporte_est'),
            'id_usuario_reportante' => $this->docenteId,
            'id_estudiante' => $this->context['id_estudiante'],
            'descripcion' => 'Documento no válido.',
            'evidencia_url' => null,
            'evidencia_public_id' => null,
            'fecha_revision' => null,
        ]);
        $this->assertDatabaseHas('log', [
            'tabla_afectada' => 'reporte_estudiante',
            'id_usuario' => $this->docenteId,
            'id_accion' => DB::table('accion')->where('operacion', 'CREAR')->value('id_accion'),
        ]);
        $this->assertSame(0, DB::table('central_riesgo')->count());
    }

    public function testAcceptsStudentWhoDoesNotBelongToExam(): void
    {
        $this->assertSame(0, DB::table('grupo_examen')->where('id_examen', $this->exam->id_examen)->count());
        DB::table('intento_ingreso')->where('id_intento', $this->attemptId)->update(['motivo' => 'NO_PERTENECE']);
        $this->postJson($this->url(), $this->payload())->assertCreated();
    }

    public function testStoresEvidenceOnLocalDiskWithRiskCenterPath(): void
    {
        $response = $this->post($this->url(), $this->payload([
            'evidencia' => UploadedFile::fake()->image('documento.png'),
        ]), ['Accept' => 'application/json'])->assertCreated();

        $report = StudentReport::query()->findOrFail($response->json('data.id_reporte_est'));
        Storage::disk('local')->assertExists($report->evidencia_public_id);
        $this->assertStringStartsWith('evidencias/reportes/', $report->evidencia_public_id);
        $this->assertSame($report->evidencia_public_id, $report->evidencia_url);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function testAcceptsPdfEvidence(): void
    {
        $response = $this->post($this->url(), $this->payload([
            'evidencia' => UploadedFile::fake()->create('documento.pdf', 100, 'application/pdf'),
        ]), ['Accept' => 'application/json'])->assertCreated();

        $report = StudentReport::query()->findOrFail($response->json('data.id_reporte_est'));
        Storage::disk('local')->assertExists($report->evidencia_public_id);
        $this->assertStringStartsWith('evidencias/reportes/', $report->evidencia_public_id);
        $this->assertSame($report->evidencia_public_id, $report->evidencia_url);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function testRejectsInvalidOrOversizedEvidence(): void
    {
        foreach ([
            UploadedFile::fake()->create('video.mp4', 100, 'video/mp4'),
            UploadedFile::fake()->create('grande.pdf', 5121, 'application/pdf'),
        ] as $file) {
            $this->post($this->url(), $this->payload(['evidencia' => $file]), ['Accept' => 'application/json'])
                ->assertStatus(422)->assertJsonValidationErrors('evidencia');
        }

        $this->assertSame(0, StudentReport::query()->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function testRequiresTypeAndDescriptionWithinBounds(): void
    {
        $this->postJson($this->url(), [])->assertStatus(422)
            ->assertJsonValidationErrors(['id_tipo_falta', 'descripcion']);

        foreach (['corto', str_repeat('a', 501), str_repeat(' ', 12)] as $description) {
            $this->postJson($this->url(), $this->payload(['descripcion' => $description]))
                ->assertStatus(422)->assertJsonValidationErrors('descripcion');
        }
    }

    public function testRejectsDuplicateWithoutAnotherReportOrAudit(): void
    {
        $this->postJson($this->url(), $this->payload())->assertCreated();
        $logCount = DB::table('log')->count();
        $this->postJson($this->url(), $this->payload())->assertStatus(422)
            ->assertJsonValidationErrors('id_intento');
        $this->assertSame(1, StudentReport::query()->count());
        $this->assertSame($logCount, DB::table('log')->count());
    }

    public function testCannotReportAgainAfterTeacherRejectsReport(): void
    {
        $this->insertStudentReport($this->context, [
            'id_intento' => $this->attemptId,
            'estado' => 'RECHAZADO',
            'fecha_revision' => now(),
            'id_usuario_revisor' => $this->docenteId,
            'observacion_revision' => 'El caso no corresponde.',
        ]);
        $this->postJson($this->url(), $this->payload())->assertStatus(422)
            ->assertJsonValidationErrors('id_intento');
    }

    public function testCannotReportUnidentifiedAttempt(): void
    {
        $id = $this->insertEntryAttempt($this->context, ['id_estudiante' => null, 'motivo' => 'NO_ENCONTRADO']);
        $this->postJson($this->url($id), $this->payload())->assertStatus(422)
            ->assertJsonValidationErrors('estudiante');
    }

    public function testRejectsAttemptFromAnotherExam(): void
    {
        $other = $this->createExam(['estado' => Exam::EN_INGRESO], [$this->aulaId]);
        $id = $this->insertEntryAttempt($this->context, ['id_examen' => $other->id_examen]);
        $this->postJson($this->url($id), $this->payload())->assertNotFound();
    }

    public function testRequiresAnAuthorizedController(): void
    {
        $this->actingAs(User::query()->findOrFail($this->otroDocenteId));
        $this->postJson($this->url(), $this->payload())->assertForbidden();
    }

    public function testAuxiliaryCanOnlyReportAttemptsFromAssignedRoom(): void
    {
        DB::table('usuario_rol')->where('id_usuario', $this->otroDocenteId)->update([
            'id_rol' => Role::query()->where('nombre_rol', Role::AUXILIAR)->value('id_rol'),
        ]);
        DB::table('examen_auxiliar')->insert([
            'id_examen' => $this->exam->id_examen,
            'id_usuario' => $this->otroDocenteId,
            'id_usuario_docente_habilita' => $this->docenteId,
            'id_ambiente' => $this->aulaId,
        ]);
        $this->actingAs(User::query()->findOrFail($this->otroDocenteId));
        $this->postJson($this->url(), $this->payload())->assertCreated();
        $this->assertDatabaseHas('reporte_estudiante', ['id_usuario_reportante' => $this->otroDocenteId]);
        $otherRoomAttempt = $this->insertEntryAttempt($this->context, ['id_ambiente' => $this->otraAulaId]);
        $this->postJson($this->url($otherRoomAttempt), $this->payload())->assertForbidden();
    }

    public function testRejectsClosedExam(): void
    {
        $this->exam->update(['estado' => Exam::FINALIZADO]);
        $this->postJson($this->url(), $this->payload())->assertStatus(409);
    }

    public function testDoesNotAcceptClientAssignedStudentOrState(): void
    {
        $this->postJson($this->url(), $this->payload([
            'id_estudiante' => $this->context['id_estudiante'], 'estado' => 'APROBADO',
        ]))->assertStatus(422)->assertJsonValidationErrors(['id_estudiante', 'estado']);
    }

    public function testRejectsUnboundedIdsWithoutDatabaseError(): void
    {
        $this->postJson($this->url(), $this->payload(['id_tipo_falta' => '999999999999999999999']))
            ->assertStatus(422)->assertJsonValidationErrors('id_tipo_falta');
        $this->postJson('/api/control-ingreso/examenes/99999999999999/intentos/1/reporte', $this->payload())
            ->assertNotFound();
        $this->postJson('/api/control-ingreso/examenes/1/intentos/99999999999999/reporte', $this->payload())
            ->assertNotFound();
    }

    public function testRollsBackReportAndRemovesEvidenceWhenAuditFails(): void
    {
        $this->mock(AuditLogService::class, function ($mock): void {
            $mock->shouldReceive('registrar')->once()->andThrow(new RuntimeException('Fallo simulado de bitácora'));
        });
        $this->withoutExceptionHandling();

        try {
            $this->post($this->url(), $this->payload([
                'evidencia' => UploadedFile::fake()->image('documento.png'),
            ]), ['Accept' => 'application/json']);
            $this->fail('La bitácora debía impedir la creación.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Fallo simulado de bitácora', $exception->getMessage());
        }

        $this->assertSame(0, StudentReport::query()->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function testRequiresAuthentication(): void
    {
        $this->actAsGuest();
        $this->postJson($this->url(), $this->payload())->assertUnauthorized();
    }

    public function testListsAttemptsWithPersistentReportFlag(): void
    {
        $this->postJson($this->url(), $this->payload())->assertCreated();
        $url = "/api/control-ingreso/examenes/{$this->exam->id_examen}/intentos?id_ambiente={$this->aulaId}";
        $this->getJson($url)->assertOk()->assertJsonPath('data.0.reportado', true)
            ->assertJsonPath('data.0.id_intento', $this->attemptId);
        $this->getJson($url)->assertOk()->assertJsonPath('data.0.reportado', true);
    }

    public function testListsOnlyRequestedRoomIncludingUnidentifiedAttempts(): void
    {
        $this->insertEntryAttempt($this->context, ['id_ambiente' => $this->otraAulaId]);
        $unknown = $this->insertEntryAttempt($this->context, [
            'id_estudiante' => null, 'cod_sis' => '202399999', 'motivo' => 'NO_ENCONTRADO',
        ]);
        $url = "/api/control-ingreso/examenes/{$this->exam->id_examen}/intentos?id_ambiente={$this->aulaId}";
        $this->getJson($url)->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id_intento', $unknown)
            ->assertJsonPath('data.0.estudiante', null)
            ->assertJsonPath('data.0.cod_sis', '202399999');
    }

    public function testCannotListAnotherControllersRoom(): void
    {
        $this->actingAs(User::query()->findOrFail($this->otroDocenteId));
        $url = "/api/control-ingreso/examenes/{$this->exam->id_examen}/intentos?id_ambiente={$this->aulaId}";
        $this->getJson($url)->assertForbidden();
    }
}
