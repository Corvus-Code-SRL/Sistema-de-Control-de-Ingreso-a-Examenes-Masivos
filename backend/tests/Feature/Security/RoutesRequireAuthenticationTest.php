<?php

namespace Tests\Feature\Security;

use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * RNF-02: el login es la única ruta pública de la API. Recorre TODAS las rutas registradas, así
 * que una ruta nueva que quede fuera del grupo auth:sanctum hace fallar esta prueba sin tocarla.
 */
class RoutesRequireAuthenticationTest extends TestCase
{
    private const PUBLIC_ROUTE = 'POST api/auth/login';

    public function test_toda_ruta_de_la_api_salvo_el_login_declara_auth_sanctum(): void
    {
        $unprotected = [];

        foreach ($this->apiRoutes() as $route) {
            if ($this->signature($route) === self::PUBLIC_ROUTE) {
                continue;
            }

            if (! in_array('auth:sanctum', $route->gatherMiddleware(), true)) {
                $unprotected[] = $this->signature($route);
            }
        }

        $this->assertSame([], $unprotected, "Rutas de la API sin auth:sanctum:\n" . implode("\n", $unprotected));
    }

    public function test_sin_token_toda_ruta_salvo_el_login_responde_401(): void
    {
        $this->actAsGuest();
        $walked = 0;
        $failures = [];

        foreach ($this->apiRoutes() as $route) {
            if ($this->signature($route) === self::PUBLIC_ROUTE) {
                continue;
            }

            // Sin `Accept: application/json` a propósito: también debe ser un 401, nunca un 500.
            $method = $this->requestMethod($route);
            $status = $this->call($method, $this->concreteUri($route))->getStatusCode();
            $walked++;

            if ($status !== 401) {
                $failures[] = "{$method} {$route->uri()} respondió {$status}";
            }
        }

        $this->assertGreaterThan(40, $walked, 'El recorrido no encontró las rutas esperadas.');
        $this->assertSame([], $failures, "Rutas que no exigen token:\n" . implode("\n", $failures));
    }

    public function test_el_login_es_la_unica_ruta_publica_y_esta_limitada_a_10_intentos_por_minuto(): void
    {
        $public = [];

        foreach ($this->apiRoutes() as $route) {
            if (! in_array('auth:sanctum', $route->gatherMiddleware(), true)) {
                $public[] = $route;
            }
        }

        $this->assertCount(1, $public);
        $this->assertSame(self::PUBLIC_ROUTE, $this->signature($public[0]));
        $this->assertContains('throttle:10,1', $public[0]->gatherMiddleware());
    }

    /** @return array<int, LaravelRoute> */
    private function apiRoutes(): array
    {
        return array_values(array_filter(
            Route::getRoutes()->getRoutes(),
            fn (LaravelRoute $route) => strpos($route->uri(), 'api/') === 0
        ));
    }

    private function signature(LaravelRoute $route): string
    {
        return $this->requestMethod($route) . ' ' . $route->uri();
    }

    private function requestMethod(LaravelRoute $route): string
    {
        return array_values(array_diff($route->methods(), ['HEAD']))[0];
    }

    /** Sustituye cada {parametro} por un valor que cumpla su restricción (uuid o entero). */
    private function concreteUri(LaravelRoute $route): string
    {
        return '/' . preg_replace_callback('/\{(\w+)\??\}/', function (array $match) use ($route) {
            $pattern = $route->wheres[$match[1]] ?? '';

            return strpos($pattern, '{12}') !== false
                ? '00000000-0000-4000-8000-000000000001'
                : '1';
        }, $route->uri());
    }
}
