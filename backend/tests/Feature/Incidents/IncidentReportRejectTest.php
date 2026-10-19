<?php

namespace Tests\Feature\Incidents;

use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\AssignsIncidentRole;
use Tests\Concerns\SeedsRiskCenterRecords;
use Tests\TestCase;
use App\Models\Action;
use LogicException;

class IncidentReportRejectTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsRiskCenterRecords;
    use AssignsIncidentRole;

    private array $context;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(\Database\Seeders\ActionSeeder::class);

        $this->context = $this->createReportingContext();
        $this->assignDocenteRole($this->context['id_usuario']);
        $this->actAsUserId($this->context['id_usuario']);
    }

    public function test_rechaza_con_observacion_y_no_entra_a_central_riesgo(): void
    {
        $reporteId = $this->insertStudentReport($this->context);

        $response = $this->postJson(
            "/api/docente/incidencias/estudiante-{$reporteId}/rechazar",
            ['observacion' => 'Sin evidencia suficiente.']
        );

        $response->assertOk()
            ->assertJsonPath('data.estado', 'RECHAZADO')
            ->assertJsonPath('data.en_central_riesgo', false)
            ->assertJsonPath('data.revisor.observacion_revision', 'Sin evidencia suficiente.');

        $this->assertDatabaseMissing('central_riesgo', [
            'id_reporte_est' => $reporteId,
        ]);
    }

    public function test_sin_observacion_devuelve_422_con_mensaje_exacto(): void
    {
        $reporteId = $this->insertStudentReport($this->context);

        $response = $this->postJson(
            "/api/docente/incidencias/estudiante-{$reporteId}/rechazar",
            []
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors('observacion')
            ->assertJsonPath('errors.observacion.0', 'La observación es obligatoria.');
    }

    public function test_segundo_rechazo_devuelve_409(): void
    {
        $reporteId = $this->insertStudentReport($this->context);

        $this->postJson(
            "/api/docente/incidencias/estudiante-{$reporteId}/rechazar",
            ['observacion' => 'no']
        )->assertOk();

        $this->postJson(
            "/api/docente/incidencias/estudiante-{$reporteId}/rechazar",
            ['observacion' => 'no']
        )->assertStatus(409);
    }

    public function test_rechazar_aprobado_devuelve_409(): void
    {
        $reporteId = $this->insertStudentReport($this->context, [
            'estado'                => 'APROBADO',
            'fecha_revision'        => '2026-10-01 10:00:00+00',
            'id_usuario_revisor'    => $this->context['id_usuario'],
            'observacion_revision'  => 'ok',
        ]);

        $this->postJson(
            "/api/docente/incidencias/estudiante-{$reporteId}/rechazar",
            ['observacion' => 'no']
        )->assertStatus(409);
    }

    public function test_otro_docente_no_puede_rechazar(): void
    {
        $otro = $this->createReportingContext();
        $reporteId = $this->insertStudentReport($otro);

        $this->postJson(
            "/api/docente/incidencias/estudiante-{$reporteId}/rechazar",
            ['observacion' => 'no']
        )->assertForbidden();
    }

    public function test_auditoria_registra_rechazar_reporte(): void
    {
        $reporteId = $this->insertStudentReport($this->context);

        $this->postJson(
            "/api/docente/incidencias/estudiante-{$reporteId}/rechazar",
            ['observacion' => 'no']
        )->assertOk();

        $log = DB::table('log')
            ->join('accion', 'accion.id_accion', '=', 'log.id_accion')
            ->where('log.id_usuario', $this->context['id_usuario'])
            ->where('accion.operacion', 'RECHAZAR_REPORTE')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('reporte_estudiante', $log->tabla_afectada);
    }

    public function test_sin_accion_rechazar_report_no_actualiza_ni_audita(): void
    {
        DB::table('accion')->where('operacion', 'RECHAZAR_REPORTE')->delete();

        $reporteId = $this->insertStudentReport($this->context);

        $response = $this->postJson(
            "/api/docente/incidencias/estudiante-{$reporteId}/rechazar",
            ['observacion' => 'no']
        );

        $response->assertStatus(500);

        $this->assertDatabaseHas('reporte_estudiante', [
            'id_reporte_est' => $reporteId,
            'estado' => 'PENDIENTE',
        ]);
    }
}
