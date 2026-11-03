<?php

namespace Tests\Feature\Incidents;

use App\Models\Exam;
use App\Services\Exams\ExamLifecycleService;
use Carbon\Carbon;
use Database\Seeders\IncidentTypeSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SeedsExamSituations;
use Tests\TestCase;

/**
 * HU-042 frente al ciclo de vida del examen: solo se reporta En curso (también en la prórroga hasta el
 * cierre automático), un POST tras finalizar responde 409 sin escribir nada, y las incidencias alimentan
 * el modal de terminar (HU-038-B) y el historial (HU-052).
 */
class InProgressIncidentLifecycleTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsExamSituations;

    private const FINISHED_MESSAGE = 'El examen ya finalizó; no se pueden reportar incidencias.';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedExamSituations();
        $this->seed(IncidentTypeSeeder::class);
        Storage::fake('local');
        $this->situationExam->update([
            'fecha' => '2026-10-01', 'hora_inicio' => '10:04', 'hora_fin' => '11:34', 'duracion' => 90,
        ]);
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
            'descripcion' => 'Usó un celular durante el examen sin autorización.',
            'id_ambiente' => $this->aulaId,
            'retirar' => false,
        ], $overrides);
    }

    private function deadline(): Carbon
    {
        return Carbon::parse('2026-10-01 11:44:00', config('sciem.zona_horaria'));
    }

    private function assertNothingWritten(int $reports, int $logs): void
    {
        $this->assertSame($reports, DB::table('reporte_estudiante')->count());
        $this->assertSame($logs, DB::table('log')->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertNull($this->attendanceExpulsion());
    }

    private function attendanceExpulsion(): ?string
    {
        return DB::table('examen_estudiante')->where('id_examen', $this->situationExam->id_examen)
            ->where('id_estudiante', $this->situationStudent->id_estudiante)->value('expulsado_en');
    }

    public function testReportAfterManualFinishIsRejectedWithoutPartialWrites(): void
    {
        app(ExamLifecycleService::class)->finishManually($this->situationExam, $this->docenteId);
        $logs = DB::table('log')->count();

        $this->postJson($this->url(), $this->payload([
            'retirar' => true,
            'evidencia' => \Illuminate\Http\UploadedFile::fake()->image('prueba.png'),
        ]))->assertStatus(409)->assertJsonPath('message', self::FINISHED_MESSAGE);

        $this->assertNothingWritten(0, $logs);
    }

    public function testReportAfterAutomaticFinishIsRejectedWithoutPartialWrites(): void
    {
        $this->assertSame(1, app(ExamLifecycleService::class)->finishExpired($this->deadline()));
        $this->assertTrue($this->situationExam->fresh()->fin_automatico);
        $logs = DB::table('log')->count();

        $this->postJson($this->url(), $this->payload(['retirar' => true]))
            ->assertStatus(409)->assertJsonPath('message', self::FINISHED_MESSAGE);

        $this->assertNothingWritten(0, $logs);
    }

    public function testReportIsAcceptedDuringTheGracePeriodBeforeTheAutomaticClose(): void
    {
        // El examen terminó a las 11:34 pero sigue «En curso» hasta las 11:44.
        Carbon::setTestNow(Carbon::parse('2026-10-01 11:40:00', config('sciem.zona_horaria')));

        $this->postJson($this->url(), $this->payload())->assertCreated();

        $this->assertSame(0, app(ExamLifecycleService::class)->finishExpired(Carbon::now()));
        $this->assertSame(1, DB::table('reporte_estudiante')->count());
    }

    public function testReportReceivedAfterTheDeadlineIsRejectedOnceTheJobClosedTheExam(): void
    {
        Carbon::setTestNow($this->deadline());
        app(ExamLifecycleService::class)->finishExpired(Carbon::now());

        $this->postJson($this->url(), $this->payload())->assertStatus(409);
        $this->assertSame(Exam::FINALIZADO, $this->situationExam->fresh()->estado);
    }

    public function testPendingReportsFeedTheFinishModalTotalAndTheHistory(): void
    {
        $this->postJson($this->url(), $this->payload())->assertCreated();
        $this->postJson($this->url(), $this->payload([
            'descripcion' => 'Después copió de otra hoja del compañero.',
            'retirar' => true,
        ]))->assertCreated();

        // HU-038-B: el modal de terminar muestra este total.
        $this->getJson("/api/examenes/{$this->situationExam->id_examen}/en-curso")->assertOk()
            ->assertJsonPath('data.totales.incidencias_pendientes', 2)
            ->assertJsonPath('data.totales.expulsados', 1);

        app(ExamLifecycleService::class)->finishManually($this->situationExam, $this->docenteId);

        // HU-052: total y línea de tiempo del historial.
        $detail = $this->getJson("/api/historial/examenes/{$this->situationExam->id_examen}")->assertOk();
        $this->assertSame(2, $detail->json('data.totales.incidencias'));
        $events = collect($detail->json('data.linea_tiempo'))->where('tipo', 'incidencia');
        $this->assertCount(2, $events);

        $list = collect($this->getJson('/api/historial/examenes')->assertOk()->json('data.examenes'))
            ->firstWhere('id_examen', $this->situationExam->id_examen);
        $this->assertSame(2, $list['incidencias']);
    }

    public function testExpelledStudentShowsAsExpelledInTheFinalReport(): void
    {
        $this->postJson($this->url(), $this->payload(['retirar' => true]))->assertCreated();
        $expelledAt = Carbon::parse($this->attendanceExpulsion())
            ->setTimezone(config('sciem.zona_horaria'))->format('H:i');
        app(ExamLifecycleService::class)->finishManually($this->situationExam, $this->docenteId);

        $states = array_column(
            $this->getJson("/api/examenes/{$this->situationExam->id_examen}/reporte-final")
                ->assertOk()->json('data.estudiantes'),
            'estado'
        );

        $this->assertContains("Expulsado · {$expelledAt}", $states);
        $this->assertNotContains('A tiempo', $states);
    }
}
