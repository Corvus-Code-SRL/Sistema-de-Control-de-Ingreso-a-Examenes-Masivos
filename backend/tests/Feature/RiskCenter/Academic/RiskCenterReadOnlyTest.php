<?php

namespace Tests\Feature\RiskCenter\Academic;

use App\Models\Role;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\SeedsRiskCenterRecords;
use Tests\Concerns\SeedsSecurityAccounts;
use Tests\TestCase;

/**
 * HU-046: la consulta es de solo lectura. No existe ninguna ruta que escriba bajo /api/central-riesgo.
 */
class RiskCenterReadOnlyTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsRiskCenterRecords;
    use SeedsSecurityAccounts;

    private const WRITE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function test_las_rutas_de_la_central_solo_aceptan_lectura(): void
    {
        $routes = $this->riskCenterRoutes();

        $this->assertNotEmpty($routes, 'No se encontró ninguna ruta bajo /api/central-riesgo.');

        foreach ($routes as $route) {
            $this->assertSame([], array_intersect(self::WRITE_METHODS, $route->methods()), $route->uri());
        }
    }

    public function test_escribir_sobre_la_central_no_es_posible_ni_para_el_administrador(): void
    {
        $this->seedSecurityAccounts();
        $context = $this->createReportingContext();
        $record = $this->insertApprovedRecord($context);
        $id = $record['id_cr_estudiante'];
        $this->actAs($this->createAccount(Role::ADMINISTRADOR));

        foreach (['/api/central-riesgo', "/api/central-riesgo/{$id}", "/api/central-riesgo/{$id}/evidencia"] as $url) {
            foreach (['postJson', 'putJson', 'patchJson', 'deleteJson'] as $method) {
                $status = $this->{$method}($url, ['id_estudiante' => 1])->getStatusCode();

                $this->assertContains($status, [404, 405], "{$method} {$url} respondió {$status}");
            }
        }

        $this->assertDatabaseHas('central_riesgo', ['id_cr_estudiante' => $id]);
    }

    /** @return array<int, LaravelRoute> */
    private function riskCenterRoutes(): array
    {
        return array_values(array_filter(
            Route::getRoutes()->getRoutes(),
            fn (LaravelRoute $route) => strpos($route->uri(), 'api/central-riesgo') === 0
        ));
    }
}
