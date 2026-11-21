<?php

namespace Tests\Feature\RiskCenter\Academic\Rectification;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SeedsRectifications;
use Tests\Concerns\SeedsRiskCenterRecords;
use Tests\Concerns\SeedsSecurityAccounts;
use Tests\TestCase;

/**
 * HU-062: cada registro activo que ve el Administrador trae el resumen de sus solicitudes de rectificación:
 * la pendiente con el docente y las fechas, cuántas se rechazaron y cuántas vencieron (por separado) y el último
 * rechazo con su motivo, su fecha y su evidencia. Un registro con solicitudes sigue activo.
 */
class RectificationSummaryTest extends TestCase
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

        Storage::fake('local');
        $this->seedSecurityAccounts();
        $this->context = $this->createReportingContext();
        $this->recordId = $this->insertApprovedRecord($this->context)['id_cr_estudiante'];
        $this->makeApproverActive($this->recordId);
    }

    private function summary(): array
    {
        $records = $this->getJson("/api/rectificacion-riesgo/estudiantes/{$this->context['id_estudiante']}/registros")
            ->assertOk()
            ->json('data.registros');

        return collect($records)->firstWhere('id_cr_estudiante', $this->recordId)['rectificacion'];
    }

    private function rejection(int $daysAgo, string $reason, ?string $evidence = null): int
    {
        return $this->insertRectificationRequest($this->recordId, [
            'estado' => 'RECHAZADA',
            'fecha_solicitud' => now()->subDays($daysAgo + 2),
            'vence_en' => now()->addDays(5 - $daysAgo),
            'fecha_respuesta' => now()->subDays($daysAgo),
            'motivo_rechazo' => $reason,
            'evidencia_public_id' => $evidence,
        ]);
    }

    public function test_sin_solicitudes_el_resumen_esta_en_cero(): void
    {
        $summary = $this->summary();

        $this->assertNull($summary['solicitud_pendiente']);
        $this->assertSame(0, $summary['rechazos']);
        $this->assertSame(0, $summary['vencidas']);
        $this->assertNull($summary['ultimo_rechazo']);
        $this->assertSame(7, $summary['dias_vencimiento']);
        $this->assertSame(180, $summary['dias_docente_inactivo']);
        $this->assertTrue($summary['docente']['activo']);
    }

    public function test_una_pendiente_muestra_el_docente_la_fecha_de_solicitud_y_la_de_vencimiento(): void
    {
        $id = $this->insertRectificationRequest($this->recordId);

        $pending = $this->summary()['solicitud_pendiente'];

        $this->assertSame($id, $pending['id_solicitud_rect']);
        $this->assertSame('Docente Reportante', $pending['docente']['nombre_completo']);
        $this->assertNotNull($pending['fecha_solicitud']);
        $this->assertNotNull($pending['vence_en']);
        $this->assertSame('Se solicita retirar el registro por un error de identificación.', $pending['motivo']);
    }

    public function test_cuenta_los_rechazos_y_los_vencimientos_por_separado_y_muestra_el_ultimo_rechazo(): void
    {
        Storage::disk('local')->put('evidencias/rectificaciones/ultimo.png', 'contenido');
        $this->rejection(30, 'Primer rechazo.');
        $last = $this->rejection(10, 'Último rechazo con motivo.', 'evidencias/rectificaciones/ultimo.png');
        $this->insertRectificationRequest($this->recordId, [
            'estado' => 'VENCIDA',
            'fecha_solicitud' => now()->subDays(60),
            'vence_en' => now()->subDays(53),
        ]);
        $this->insertRectificationRequest($this->recordId, [
            'fecha_solicitud' => now()->subDays(40),
            'vence_en' => now()->subDays(33),
        ]);

        $summary = $this->summary();

        $this->assertSame(2, $summary['rechazos']);
        $this->assertSame(2, $summary['vencidas'], 'Cuenta la vencida marcada y la pendiente pasada de fecha.');
        $this->assertNull($summary['solicitud_pendiente']);
        $this->assertSame($last, $summary['ultimo_rechazo']['id_solicitud_rect']);
        $this->assertSame('Último rechazo con motivo.', $summary['ultimo_rechazo']['motivo']);
        $this->assertNotNull($summary['ultimo_rechazo']['fecha']);
        $this->assertSame('ultimo.png', $summary['ultimo_rechazo']['evidencia']['nombre_archivo']);
        $this->assertTrue($summary['ultimo_rechazo']['evidencia']['disponible']);

        $this->get("/api/rectificacion-riesgo/solicitudes/{$last}/evidencia")->assertOk();
    }

    public function test_el_ultimo_rechazo_sin_imagen_no_trae_evidencia(): void
    {
        $this->rejection(3, 'Rechazo sin imagen adjunta.');

        $this->assertNull($this->summary()['ultimo_rechazo']['evidencia']);
    }

    public function test_el_resumen_solo_cuenta_las_solicitudes_del_propio_registro(): void
    {
        $other = $this->insertApprovedRecord($this->context)['id_cr_estudiante'];
        $this->makeApproverActive($other);
        $this->insertRectificationRequest($other, [
            'estado' => 'RECHAZADA',
            'fecha_respuesta' => now(),
            'motivo_rechazo' => 'Rechazo de otro registro.',
        ]);

        $this->assertSame(0, $this->summary()['rechazos']);
    }

    public function test_un_registro_con_solicitudes_sigue_activo_en_todas_las_lecturas_de_la_central(): void
    {
        $this->rejection(5, 'Rechazo registrado.');
        $expiredRecord = $this->insertApprovedRecord($this->context)['id_cr_estudiante'];
        $this->insertRectificationRequest($expiredRecord, [
            'fecha_solicitud' => now()->subDays(9),
            'vence_en' => now()->subDays(2),
        ]);
        $pendingRecord = $this->insertApprovedRecord($this->context)['id_cr_estudiante'];
        $this->insertRectificationRequest($pendingRecord);

        $listed = array_column($this->getJson('/api/central-riesgo')->assertOk()->json('data'), 'id_cr_estudiante');
        foreach ([$this->recordId, $expiredRecord, $pendingRecord] as $id) {
            $this->assertContains($id, $listed);
            $this->getJson("/api/central-riesgo/{$id}")->assertOk();
            $this->assertNull($this->recordRow($id)['rectificado_en']);
        }

        $student = \App\Models\Student::query()->find($this->context['id_estudiante']);

        $this->assertSame(3, $student->riskCenters()->count());
        $this->assertSame(3, DB::table('central_riesgo')->whereNull('rectificado_en')->count());
    }
}
