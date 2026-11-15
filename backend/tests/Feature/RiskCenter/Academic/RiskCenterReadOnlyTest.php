<?php

namespace Tests\Feature\RiskCenter\Academic;

use App\Models\Role;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\SeedsRectifications;
use Tests\Concerns\SeedsRiskCenterRecords;
use Tests\Concerns\SeedsSecurityAccounts;
use Tests\TestCase;

/**
 * HU-046: la consulta es de solo lectura. No existe ninguna ruta que escriba bajo /api/central-riesgo.
 *
 * HU-062: la única escritura sobre la Central es crear una versión nueva en /api/rectificacion-riesgo; no hay
 * rutas para editar ni borrar un registro ni sus versiones.
 */
class RiskCenterReadOnlyTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsRectifications;
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

    public function test_la_rectificacion_solo_expone_lecturas_y_un_unico_post(): void
    {
        $writes = [];
        $routes = $this->routesUnder('api/rectificacion-riesgo');

        $this->assertNotEmpty($routes, 'No se encontró ninguna ruta bajo /api/rectificacion-riesgo.');

        foreach ($routes as $route) {
            foreach (array_intersect(self::WRITE_METHODS, $route->methods()) as $method) {
                $writes[] = $method . ' ' . $route->uri();
            }
        }

        $this->assertSame(['POST api/rectificacion-riesgo/registros/{id}/rectificar'], $writes);
    }

    public function test_ninguna_ruta_de_la_api_edita_o_borra_registros_o_versiones_de_la_central(): void
    {
        foreach (Route::getRoutes()->getRoutes() as $route) {
            $isRiskUri = strpos($route->uri(), 'central-riesgo') !== false
                || strpos($route->uri(), 'rectificacion-riesgo') !== false;

            if ($isRiskUri) {
                $this->assertSame([], array_intersect(['PUT', 'PATCH', 'DELETE'], $route->methods()), $route->uri());
            }
        }
    }

    public function test_editar_o_borrar_un_registro_o_una_version_no_es_posible_ni_para_el_administrador(): void
    {
        $this->seedSecurityAccounts();
        $context = $this->createReportingContext();
        $id = $this->insertApprovedRecord($context)['id_cr_estudiante'];
        $this->rectify($id)->assertCreated();
        $versionId = (int) DB::table('central_riesgo_version')->where('id_cr_estudiante', $id)->value('id_version');
        $recordBefore = (array) DB::table('central_riesgo')->where('id_cr_estudiante', $id)->first();
        $versionsBefore = $this->versionsOf($id);

        $urls = [
            "/api/rectificacion-riesgo/registros/{$id}",
            "/api/rectificacion-riesgo/registros/{$id}/rectificar",
            "/api/rectificacion-riesgo/registros/{$id}/versiones",
            "/api/rectificacion-riesgo/registros/{$id}/versiones/{$versionId}",
            "/api/rectificacion-riesgo/versiones/{$versionId}",
            "/api/rectificacion-riesgo/estudiantes/{$context['id_estudiante']}/registros",
        ];

        foreach ($urls as $url) {
            foreach (['putJson', 'patchJson', 'deleteJson'] as $method) {
                $status = $this->{$method}($url, ['id_tipo_falta' => 1, 'motivo' => 'Intento de edición'])
                    ->getStatusCode();

                $this->assertContains($status, [404, 405], "{$method} {$url} respondió {$status}");
            }
        }

        $this->assertSame($recordBefore, (array) DB::table('central_riesgo')->where('id_cr_estudiante', $id)->first());
        $this->assertSame($versionsBefore, $this->versionsOf($id));
    }

    /** @return array<int, LaravelRoute> */
    private function routesUnder(string $prefix): array
    {
        return array_values(array_filter(
            Route::getRoutes()->getRoutes(),
            fn (LaravelRoute $route) => strpos($route->uri(), $prefix) === 0
        ));
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
