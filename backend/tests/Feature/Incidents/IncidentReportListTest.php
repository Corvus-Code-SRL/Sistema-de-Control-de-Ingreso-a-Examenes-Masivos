<?php

namespace Tests\Feature\Incidents;

use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\AssignsIncidentRole;
use Tests\Concerns\SeedsRiskCenterRecords;
use Tests\TestCase;

class IncidentReportListTest extends TestCase
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

    public function test_lista_los_pendientes_del_docente_en_orden_ascendente(): void
    {
        // 1. Insertar 2 pendientes: el más antiguo primero.
        $olderId = $this->insertStudentReport($this->context, [
            'fecha_reporte' => '2026-10-01 09:00:00+00',
        ]);
        $newerId = $this->insertStudentReport($this->context, [
            'fecha_reporte' => '2026-10-01 10:00:00+00',
        ]);

        // 2. Listar pendientes.
        $response = $this->getJson('/api/docente/incidencias?estado=pendiente');

        // 3. Orden ascendente por fecha_reporte.
        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', "estudiante-{$olderId}")
            ->assertJsonPath('data.1.id', "estudiante-{$newerId}")
            ->assertJsonPath('meta.total_pendientes', 2)
            ->assertJsonPath('meta.total_revisadas', 0);
    }

    public function test_sin_incidencias_devuelve_lista_vacia_y_cero_pendientes(): void
    {
        // CA6: sin reportes pendientes.
        $response = $this->getJson('/api/docente/incidencias?estado=pendiente');

        $response->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total_pendientes', 0)
            ->assertJsonPath('meta.total_revisadas', 0);
    }

    public function test_filtra_solo_revisadas(): void
    {
        $pendiente = $this->insertStudentReport($this->context);
        $aprobado  = $this->insertStudentReport($this->context, [
            'estado'                => 'APROBADO',
            'fecha_revision'        => '2026-10-01 10:00:00+00',
            'id_usuario_revisor'    => $this->context['id_usuario'],
            'observacion_revision'  => 'ok',
        ]);
        $rechazado = $this->insertStudentReport($this->context, [
            'estado'                => 'RECHAZADO',
            'fecha_revision'        => '2026-10-01 11:00:00+00',
            'id_usuario_revisor'    => $this->context['id_usuario'],
            'observacion_revision'  => 'no',
        ]);

        $response = $this->getJson('/api/docente/incidencias?estado=revisada');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total_pendientes', 1)
            ->assertJsonPath('meta.total_revisadas', 2);
    }

    public function test_meta_cuenta_todos_los_pendientes_del_docente_aunque_el_filtro_sea_revisada(): void
    {
        $this->insertStudentReport($this->context);
        $this->insertStudentReport($this->context);
        $this->insertStudentReport($this->context, [
            'estado'                => 'APROBADO',
            'fecha_revision'        => '2026-10-01 10:00:00+00',
            'id_usuario_revisor'    => $this->context['id_usuario'],
            'observacion_revision'  => 'ok',
        ]);

        $response = $this->getJson('/api/docente/incidencias?estado=revisada');

        $response->assertOk()
            ->assertJsonPath('meta.total_pendientes', 2)
            ->assertJsonPath('meta.total_revisadas', 1);
    }

    public function test_no_ve_los_reportes_de_otro_docente(): void
    {
        // Reporte del docente autenticado.
        $this->insertStudentReport($this->context);

        // Reporte de otro docente.
        $otro = $this->createReportingContext();
        $this->insertStudentReport($otro);

        $response = $this->getJson('/api/docente/incidencias?estado=pendiente');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
