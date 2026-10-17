<?php

namespace Tests\Feature\Incidents;

use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\AssignsIncidentRole;
use Tests\Concerns\SeedsRiskCenterRecords;
use Tests\TestCase;

class IncidentReportApproveTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsRiskCenterRecords;
    use AssignsIncidentRole;

    private array $context;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(\Database\Seeders\ActionSeeder::class);   // ← AÑADIR

        $this->context = $this->createReportingContext();
        $this->assignDocenteRole($this->context['id_usuario']);
        $this->actAsUserId($this->context['id_usuario']);
    }

    public function test_aprueba_y_crea_fila_en_central_riesgo(): void
    {
        $reporteId = $this->insertStudentReport($this->context);

        $response = $this->postJson(
            "/api/docente/incidencias/estudiante-{$reporteId}/aprobar",
            ['observacion' => 'Confirmado.']
        );

        $response->assertOk()
            ->assertJsonPath('data.estado', 'APROBADO')
            ->assertJsonPath('data.en_central_riesgo', true)
            ->assertJsonPath('data.revisor.observacion_revision', 'Confirmado.');

        $this->assertDatabaseHas('central_riesgo', [
            'id_reporte_est' => $reporteId,
            'id_estudiante'  => $this->context['id_estudiante'],
        ]);

        $this->assertDatabaseHas('reporte_estudiante', [
            'id_reporte_est'     => $reporteId,
            'estado'             => 'APROBADO',
            'id_usuario_revisor' => $this->context['id_usuario'],
        ]);
    }

    public function test_aprueba_sin_observacion(): void
    {
        $reporteId = $this->insertStudentReport($this->context);

        $response = $this->postJson("/api/docente/incidencias/estudiante-{$reporteId}/aprobar");

        $response->assertOk()
            ->assertJsonPath('data.estado', 'APROBADO')
            ->assertJsonPath('data.revisor.observacion_revision', null);
    }

    public function test_segunda_aprobacion_devuelve_409(): void
    {
        $reporteId = $this->insertStudentReport($this->context);

        $this->postJson("/api/docente/incidencias/estudiante-{$reporteId}/aprobar")
            ->assertOk();

        $this->postJson("/api/docente/incidencias/estudiante-{$reporteId}/aprobar")
            ->assertStatus(409);
    }

    public function test_aprobar_rechazado_devuelve_409(): void
    {
        $reporteId = $this->insertStudentReport($this->context, [
            'estado'                => 'RECHAZADO',
            'fecha_revision'        => '2026-10-01 10:00:00+00',
            'id_usuario_revisor'    => $this->context['id_usuario'],
            'observacion_revision'  => 'no',
        ]);

        $this->postJson("/api/docente/incidencias/estudiante-{$reporteId}/aprobar")
            ->assertStatus(409);
    }

    public function test_otro_docente_no_puede_aprobar(): void
    {
        $otro = $this->createReportingContext();
        $reporteId = $this->insertStudentReport($otro);

        $this->postJson("/api/docente/incidencias/estudiante-{$reporteId}/aprobar")
            ->assertForbidden();
    }

    public function test_auditoria_registra_aprobar_reporte(): void
    {
        $reporteId = $this->insertStudentReport($this->context);

        $this->postJson("/api/docente/incidencias/estudiante-{$reporteId}/aprobar")
            ->assertOk();

        $log = DB::table('log')
            ->join('accion', 'accion.id_accion', '=', 'log.id_accion')
            ->where('log.id_usuario', $this->context['id_usuario'])
            ->where('accion.operacion', 'APROBAR_REPORTE')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('reporte_estudiante', $log->tabla_afectada);
    }
}