<?php

namespace Tests\Feature\Incidents;

use Database\Seeders\ActionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\AssignsIncidentRole;
use Tests\Concerns\SeedsRiskCenterRecords;
use Tests\TestCase;

/**
 * HU-045: GET /api/docente/incidencias/{reporte}/evidencia entrega la evidencia solo
 * al docente dueño del examen, leyéndola del disco (privado o público).
 */
class IncidentReportEvidenceTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsRiskCenterRecords;
    use AssignsIncidentRole;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    private array $context;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(ActionSeeder::class);

        Storage::fake('local');
        Storage::fake('public');
        config(['sciem.evidencia_discos' => ['local', 'public']]);

        $this->context = $this->createReportingContext();
        $this->assignDocenteRole($this->context['id_usuario']);
    }

    public function test_sin_token_responde_401(): void
    {
        $this->actAsGuest();
        $this->getJson('/api/docente/incidencias/estudiante-1/evidencia')->assertUnauthorized();
    }

    public function test_el_dueno_recibe_el_archivo_con_sus_cabeceras(): void
    {
        $content = base64_decode(self::PNG);
        Storage::disk('public')->put('evidencias/reportes/foto.png', $content);

        $reporteId = $this->insertStudentReport($this->context, [
            'evidencia_url' => 'http://localhost/storage/evidencias/reportes/foto.png',
            'evidencia_public_id' => 'evidencias/reportes/foto.png',
        ]);
        $this->actAsUserId($this->context['id_usuario']);

        $response = $this->get("/api/docente/incidencias/estudiante-{$reporteId}/evidencia")->assertOk();

        $this->assertSame($content, $response->streamedContent());
        $response->assertHeader('Content-Type', 'image/png');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('inline', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_otro_docente_recibe_403(): void
    {
        Storage::disk('public')->put('evidencias/reportes/foto.png', base64_decode(self::PNG));

        $otro = $this->createReportingContext();
        $this->assignDocenteRole($otro['id_usuario']);
        $reporteId = $this->insertStudentReport($otro, [
            'evidencia_url' => 'http://localhost/storage/evidencias/reportes/foto.png',
            'evidencia_public_id' => 'evidencias/reportes/foto.png',
        ]);
        $this->actAsUserId($this->context['id_usuario']);

        $this->getJson("/api/docente/incidencias/estudiante-{$reporteId}/evidencia")->assertForbidden();
    }

    public function test_sin_evidencia_responde_404(): void
    {
        $reporteId = $this->insertStudentReport($this->context);
        $this->actAsUserId($this->context['id_usuario']);

        $this->getJson("/api/docente/incidencias/estudiante-{$reporteId}/evidencia")->assertNotFound();
    }

    public function test_si_el_archivo_falta_en_los_discos_responde_404(): void
    {
        $reporteId = $this->insertStudentReport($this->context, [
            'evidencia_url' => 'http://localhost/storage/evidencias/reportes/falta.png',
            'evidencia_public_id' => 'evidencias/reportes/falta.png',
        ]);
        $this->actAsUserId($this->context['id_usuario']);

        $this->getJson("/api/docente/incidencias/estudiante-{$reporteId}/evidencia")->assertNotFound();
    }

    public function test_reporte_inexistente_responde_404(): void
    {
        $this->actAsUserId($this->context['id_usuario']);

        $this->getJson('/api/docente/incidencias/estudiante-999999/evidencia')->assertNotFound();
    }
}
