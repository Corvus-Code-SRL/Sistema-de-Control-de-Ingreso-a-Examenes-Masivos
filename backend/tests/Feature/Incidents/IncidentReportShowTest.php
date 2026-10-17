<?php

namespace Tests\Feature\Incidents;

use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\AssignsIncidentRole;
use Tests\Concerns\SeedsRiskCenterRecords;
use Tests\TestCase;

class IncidentReportShowTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsRiskCenterRecords;
    use AssignsIncidentRole;

    private array $context;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->context = $this->createReportingContext();
        $this->assignDocenteRole($this->context['id_usuario']);
        $this->actAsUserId($this->context['id_usuario']);
    }

    public function test_muestra_el_detalle_con_intento_y_evidencia(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('evidencias/reportes/test.png', str_repeat('a', 1024));

        $intentoId = $this->insertEntryAttempt($this->context, [
            'motivo'          => 'DOCUMENTO_NO_VALIDO',
            'registrado_en'   => '2026-10-01 09:15:00+00',
        ]);

        $reporteId = $this->insertStudentReport($this->context, [
            'id_intento'            => $intentoId,
            'descripcion'           => 'Descripción del reporte de prueba.',
            'evidencia_url'         => 'http://localhost:8000/storage/evidencias/reportes/test.png',
            'evidencia_public_id'   => 'evidencias/reportes/test.png',
        ]);

        $response = $this->getJson("/api/docente/incidencias/estudiante-{$reporteId}");

        $response->assertOk()
            ->assertJsonPath('data.id', "estudiante-{$reporteId}")
            ->assertJsonPath('data.estado', 'PENDIENTE')
            ->assertJsonPath('data.descripcion', 'Descripción del reporte de prueba.')
            ->assertJsonPath('data.intento.motivo', 'DOCUMENTO_NO_VALIDO')
            ->assertJsonPath('data.intento.motivo_legible', 'El documento presentado no es válido.')
            ->assertJsonPath('data.intento.hora_intento', '09:15')
            ->assertJsonPath('data.evidencia.nombre', 'test.png')
            ->assertJsonPath('data.evidencia.tipo', 'imagen')
            ->assertJsonPath('data.evidencia.tamano_bytes', 1024)
            ->assertJsonPath('data.evidencia.disponible', true)
            ->assertJsonPath('data.revisor', null)
            ->assertJsonPath('data.en_central_riesgo', false);
    }

    public function test_reporte_sin_intento_devuelve_intento_null(): void
    {
        $reporteId = $this->insertStudentReport($this->context);

        $response = $this->getJson("/api/docente/incidencias/estudiante-{$reporteId}");

        $response->assertOk()
            ->assertJsonPath('data.intento', null);
    }

    public function test_reporte_sin_evidencia_devuelve_evidencia_null(): void
    {
        $reporteId = $this->insertStudentReport($this->context);

        $response = $this->getJson("/api/docente/incidencias/estudiante-{$reporteId}");

        $response->assertOk()
            ->assertJsonPath('data.evidencia', null)
            ->assertJsonPath('data.evidencia_url', null);
    }

    public function test_reporte_inexistente_devuelve_404(): void
    {
        $response = $this->getJson('/api/docente/incidencias/estudiante-999999999');

        $response->assertNotFound();
    }

    public function test_reporte_de_otro_docente_devuelve_403(): void
    {
        $otro = $this->createReportingContext();
        $reporteId = $this->insertStudentReport($otro);

        $response = $this->getJson("/api/docente/incidencias/estudiante-{$reporteId}");

        $response->assertForbidden();
    }

    public function test_id_con_formato_invalido_devuelve_404(): void
    {
        $response = $this->getJson('/api/docente/incidencias/estudiante-abc');

        $response->assertNotFound();
    }
}