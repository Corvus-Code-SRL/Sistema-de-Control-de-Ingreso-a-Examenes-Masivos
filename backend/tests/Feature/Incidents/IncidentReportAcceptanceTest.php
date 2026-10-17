<?php

namespace Tests\Feature\Incidents;

use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\AssignsIncidentRole;
use Tests\Concerns\SeedsRiskCenterRecords;
use Tests\TestCase;

/**
 * Prueba de aceptación de HU-045: con 3 reportes pendientes, aprobar 2 y rechazar 1
 * con observación. Los 2 aprobados figuran en Revisadas como «En Central de Riesgo».
 */
class IncidentReportAcceptanceTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsRiskCenterRecords;
    use AssignsIncidentRole;

    public function test_flujo_completo_de_revision(): void
    {
        $this->seed(RoleSeeder::class);

        $context = $this->createReportingContext();
        $this->assignDocenteRole($context['id_usuario']);
        $this->actAsUserId($context['id_usuario']);

        // 1. 3 reportes pendientes del docente.
        $r1 = $this->insertStudentReport($context, ['fecha_reporte' => '2026-10-01 09:00:00+00']);
        $r2 = $this->insertStudentReport($context, ['fecha_reporte' => '2026-10-01 10:00:00+00']);
        $r3 = $this->insertStudentReport($context, ['fecha_reporte' => '2026-10-01 11:00:00+00']);

        // 2. Aprobar 2.
        $this->postJson("/api/docente/incidencias/estudiante-{$r1}/aprobar")
            ->assertOk()
            ->assertJsonPath('data.estado', 'APROBADO');
        $this->postJson("/api/docente/incidencias/estudiante-{$r2}/aprobar")
            ->assertOk()
            ->assertJsonPath('data.estado', 'APROBADO');

        // 3. Rechazar 1 con observación.
        $this->postJson(
            "/api/docente/incidencias/estudiante-{$r3}/rechazar",
            ['observacion' => 'Rechazado en la prueba de aceptación.']
        )->assertOk()
            ->assertJsonPath('data.estado', 'RECHAZADO');

        // 4. 2 filas en central_riesgo, 1 sin fila.
        $this->assertSame(2, DB::table('central_riesgo')->count());
        $this->assertDatabaseHas('central_riesgo', ['id_reporte_est' => $r1]);
        $this->assertDatabaseHas('central_riesgo', ['id_reporte_est' => $r2]);
        $this->assertDatabaseMissing('central_riesgo', ['id_reporte_est' => $r3]);

        // 5. Listar Revisadas: 3, con 2 aprobadas y 1 rechazada.
        $response = $this->getJson('/api/docente/incidencias?estado=revisada');
        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.total_pendientes', 0)
            ->assertJsonPath('meta.total_revisadas', 3);

        // 6. En la respuesta, 2 tienen en_central_riesgo = true.
        $data = $response->json('data');
        $enCR = array_filter($data, fn ($item) => $item['en_central_riesgo'] === true);
        $this->assertCount(2, $enCR);

        // 7. Listar Pendientes: 0.
        $this->getJson('/api/docente/incidencias?estado=pendiente')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}