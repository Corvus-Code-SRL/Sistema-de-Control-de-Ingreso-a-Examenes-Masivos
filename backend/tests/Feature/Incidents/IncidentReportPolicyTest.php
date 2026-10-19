<?php

namespace Tests\Feature\Incidents;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\ActionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\AssignsIncidentRole;
use Tests\Concerns\SeedsRiskCenterRecords;
use Tests\TestCase;

/**
 * HU-045: solo el docente dueño del examen puede revisar sus incidencias. El Auxiliar
 * y el Administrador reciben 403 aunque el reporte exista.
 */
class IncidentReportPolicyTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsRiskCenterRecords;
    use AssignsIncidentRole;

    private array $context;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(ActionSeeder::class);

        $this->context = $this->createReportingContext();
        $this->assignDocenteRole($this->context['id_usuario']);
    }

    /** @return array<string, array{0: string, 1: string}> */
    private function endpoints(int $reporteId): array
    {
        $reporte = "estudiante-{$reporteId}";

        return [
            'listar'        => ['GET',    '/api/docente/incidencias'],
            'detalle'       => ['GET',    "/api/docente/incidencias/{$reporte}"],
            'aprobar'       => ['POST',   "/api/docente/incidencias/{$reporte}/aprobar"],
            'rechazar'      => ['POST',   "/api/docente/incidencias/{$reporte}/rechazar"],
            'evidencia'     => ['GET',    "/api/docente/incidencias/{$reporte}/evidencia"],
        ];
    }

    public function test_un_auxiliar_recibe_403_en_todas_las_rutas(): void
    {
        $reporteId = $this->insertStudentReport($this->context);

        $auxiliar = User::create([
            'nombre' => 'Ana',
            'apellido_paterno' => 'Auxiliar',
            'correo' => 'ana.auxiliar.' . uniqid() . '@sciem.test',
            'contrasenia' => 'x',
            'cod_sis' => '202499991',
            'estado' => User::ESTADO_ACTIVO,
        ]);
        $this->assignAuxiliarRole($auxiliar->id_usuario);
        $this->actAsUserId($auxiliar->id_usuario);

        foreach ($this->endpoints($reporteId) as $label => [$method, $url]) {
            $this->json($method, $url)
                ->assertForbidden();
        }
    }

    public function test_un_administrador_recibe_403_en_todas_las_rutas(): void
    {
        $reporteId = $this->insertStudentReport($this->context);

        $admin = User::create([
            'nombre' => 'Valeria',
            'apellido_paterno' => 'Admin',
            'correo' => 'valeria.admin.' . uniqid() . '@sciem.test',
            'contrasenia' => 'x',
            'cod_sis' => 'ADM9999',
            'estado' => User::ESTADO_ACTIVO,
        ]);
        $admin->roles()->attach(Role::where('nombre_rol', Role::ADMINISTRADOR)->value('id_rol'), [
            'fecha_inicio' => now(),
        ]);
        $this->actAsUserId($admin->id_usuario);

        foreach ($this->endpoints($reporteId) as $label => [$method, $url]) {
            $this->json($method, $url)
                ->assertForbidden();
        }
    }

    public function test_un_docente_ajeno_recibe_403(): void
    {
        $otro = $this->createReportingContext();
        $this->assignDocenteRole($otro['id_usuario']);
        $reporteId = $this->insertStudentReport($otro);

        $this->actAsUserId($this->context['id_usuario']);

        $this->getJson("/api/docente/incidencias/estudiante-{$reporteId}")->assertForbidden();
        $this->postJson("/api/docente/incidencias/estudiante-{$reporteId}/aprobar")->assertForbidden();
        $this->postJson("/api/docente/incidencias/estudiante-{$reporteId}/rechazar", ['observacion' => 'no'])->assertForbidden();
        $this->getJson("/api/docente/incidencias/estudiante-{$reporteId}/evidencia")->assertForbidden();
    }

    public function test_el_docente_dueno_si_puede_listar_y_ver_el_detalle(): void
    {
        $reporteId = $this->insertStudentReport($this->context);
        $this->actAsUserId($this->context['id_usuario']);

        $this->getJson('/api/docente/incidencias')->assertOk();
        $this->getJson("/api/docente/incidencias/estudiante-{$reporteId}")->assertOk();
    }
}
