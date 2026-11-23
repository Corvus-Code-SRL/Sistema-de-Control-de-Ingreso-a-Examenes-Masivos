<?php

namespace Tests\Feature\RiskCenter\Academic\Rectification;

use App\Console\Kernel;
use App\Models\RectificationRequest;
use App\Services\RiskCenter\Academic\RectificationExpiryService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsRectifications;
use Tests\Concerns\SeedsRiskCenterRecords;
use Tests\Concerns\SeedsSecurityAccounts;
use Tests\TestCase;

/**
 * HU-062: una solicitud sin respuesta vence. La tarea programada la marca VENCIDA sin tocar el registro ni la
 * bitácora; además, toda lectura trata como vencida a una pendiente pasada de fecha.
 */
class RectificationExpiryTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsRectifications;
    use SeedsRiskCenterRecords;
    use SeedsSecurityAccounts;

    private array $context;

    private int $recordId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedSecurityAccounts();
        $this->context = $this->createReportingContext();
        $this->recordId = $this->insertApprovedRecord($this->context)['id_cr_estudiante'];
        $this->makeApproverActive($this->recordId);
    }

    private function overdue(int $recordId, array $overrides = []): int
    {
        return $this->insertRectificationRequest($recordId, array_merge([
            'fecha_solicitud' => now()->subDays(9),
            'vence_en' => now()->subDays(2),
        ], $overrides));
    }

    private function logCount(): int
    {
        return DB::table('log')->count();
    }

    public function test_la_tarea_marca_vencidas_solo_las_pendientes_pasadas_de_fecha(): void
    {
        $overdue = $this->overdue($this->recordId);
        $otherRecord = $this->insertApprovedRecord($this->context)['id_cr_estudiante'];
        $vigente = $this->insertRectificationRequest($otherRecord);
        $third = $this->insertApprovedRecord($this->context)['id_cr_estudiante'];
        $rejected = $this->insertRectificationRequest($third, [
            'estado' => 'RECHAZADA',
            'fecha_solicitud' => now()->subDays(9),
            'vence_en' => now()->subDays(2),
            'fecha_respuesta' => now()->subDays(8),
            'motivo_rechazo' => 'El reporte sí tenía sustento.',
        ]);

        $changed = app(RectificationExpiryService::class)->expireDue();

        $this->assertSame(1, $changed);
        $state = fn (int $id) => DB::table('solicitud_rectificacion')->where('id_solicitud_rect', $id)->value('estado');
        $this->assertSame('VENCIDA', $state($overdue));
        $this->assertSame('PENDIENTE', $state($vigente));
        $this->assertSame('RECHAZADA', $state($rejected));
    }

    public function test_vencer_no_anula_el_registro_ni_escribe_en_la_bitacora(): void
    {
        $this->overdue($this->recordId);
        $logs = $this->logCount();
        $before = $this->recordRow($this->recordId);

        app(RectificationExpiryService::class)->expireDue();

        $this->assertSame($before, $this->recordRow($this->recordId));
        $this->assertSame($logs, $this->logCount());
    }

    public function test_correr_la_tarea_dos_veces_no_cambia_nada_mas(): void
    {
        $this->overdue($this->recordId);

        $this->assertSame(1, app(RectificationExpiryService::class)->expireDue());
        $this->assertSame(0, app(RectificationExpiryService::class)->expireDue());
    }

    public function test_la_tarea_esta_programada_cada_hora_sin_solaparse(): void
    {
        $schedule = $this->app->make(Schedule::class);

        $event = collect($schedule->events())
            ->first(fn ($event) => $event->description === 'expire-rectification-requests');

        $this->assertNotNull($event, 'Falta la tarea programada expire-rectification-requests.');
        $this->assertSame('0 * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
    }

    public function test_el_kernel_define_la_tarea_de_vencimiento(): void
    {
        $this->assertStringContainsString('RectificationExpiryService', file_get_contents(
            (new \ReflectionClass(Kernel::class))->getFileName()
        ));
    }

    public function test_una_pendiente_pasada_de_fecha_se_lee_como_vencida_sin_que_corra_la_tarea(): void
    {
        $id = $this->overdue($this->recordId);

        $this->assertSame('PENDIENTE', DB::table('solicitud_rectificacion')->where('id_solicitud_rect', $id)
            ->value('estado'));
        $this->assertSame('VENCIDA', RectificationRequest::query()->find($id)->effectiveState());

        $this->getJson("/api/rectificacion-riesgo/registros/{$this->recordId}")
            ->assertOk()
            ->assertJsonPath('data.rectificacion.solicitud_pendiente', null)
            ->assertJsonPath('data.rectificacion.vencidas', 1)
            ->assertJsonPath('data.rectificacion.rechazos', 0);
    }

    public function test_la_bandeja_y_el_conteo_del_docente_tratan_la_pendiente_vencida_como_vencida(): void
    {
        $id = $this->overdue($this->recordId);
        $this->actAsUserId($this->approverId($this->recordId));

        $this->getJson('/api/solicitudes-rectificacion/conteo')->assertJsonPath('data.total_pendientes', 0);
        $this->getJson('/api/solicitudes-rectificacion')
            ->assertOk()
            ->assertJsonPath('data.0.id_solicitud_rect', $id)
            ->assertJsonPath('data.0.estado', 'VENCIDA')
            ->assertJsonPath('meta.total_pendientes', 0);
    }

    public function test_una_vencida_no_se_puede_responder_aunque_la_tarea_no_haya_corrido(): void
    {
        $id = $this->overdue($this->recordId);
        $this->actAsUserId($this->approverId($this->recordId));

        $this->postJson("/api/solicitudes-rectificacion/{$id}/aceptar")->assertStatus(409);

        $this->assertNull($this->recordRow($this->recordId)['rectificado_en']);
    }

    public function test_vencer_o_rechazar_no_cambian_lo_que_lee_la_central(): void
    {
        $this->overdue($this->recordId);
        app(RectificationExpiryService::class)->expireDue();
        $rejectedRecord = $this->insertApprovedRecord($this->context)['id_cr_estudiante'];
        $this->insertRectificationRequest($rejectedRecord, [
            'estado' => 'RECHAZADA',
            'fecha_respuesta' => now(),
            'motivo_rechazo' => 'El reporte sí tenía sustento.',
        ]);

        $ids = array_column($this->getJson('/api/central-riesgo')->assertOk()->json('data'), 'id_cr_estudiante');

        $this->assertContains($this->recordId, $ids);
        $this->assertContains($rejectedRecord, $ids);
    }
}
