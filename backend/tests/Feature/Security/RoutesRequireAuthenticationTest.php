<?php

namespace Tests\Feature\Security;

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsurePasswordIsChanged;
use Illuminate\Http\Request;
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

    /** Una cuenta inactiva puede cerrar su sesión: la única ruta protegida sin account.active. */
    private const LOGOUT_ROUTE = 'POST api/auth/logout';

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

    public function test_toda_ruta_protegida_exige_cuenta_activa_salvo_el_logout(): void
    {
        $withoutCheck = [];

        foreach ($this->apiRoutes() as $route) {
            $signature = $this->signature($route);

            if ($signature === self::PUBLIC_ROUTE || $signature === self::LOGOUT_ROUTE) {
                continue;
            }

            if (! in_array('account.active', $route->gatherMiddleware(), true)) {
                $withoutCheck[] = $signature;
            }
        }

        $this->assertSame(
            [],
            $withoutCheck,
            "Rutas sin account.active (una cuenta inactiva conservaría el acceso):\n" . implode("\n", $withoutCheck)
        );

        // withoutMiddleware() no quita el alias de gatherMiddleware(): lo excluye al resolver la cadena.
        $logout = Route::getRoutes()->match(Request::create('/api/auth/logout', 'POST'));

        $this->assertContains(EnsureAccountIsActive::class, $logout->excludedMiddleware());
        $this->assertContains('auth:sanctum', $logout->gatherMiddleware());

        foreach ($this->apiRoutes() as $route) {
            if ($this->signature($route) !== self::LOGOUT_ROUTE) {
                $this->assertNotContains(EnsureAccountIsActive::class, $route->excludedMiddleware());
            }
        }
    }

    /** HU-001: con la contraseña temporal pendiente solo estas rutas (y el logout) siguen respondiendo. */
    private const PASSWORD_CHANGE_ROUTES = [
        'GET api/auth/yo',
        'POST api/auth/cambiar-contrasenia',
        'POST api/auth/logout',
    ];

    public function test_toda_ruta_protegida_exige_el_cambio_de_contrasenia_salvo_tres(): void
    {
        $withoutCheck = [];
        $exempt = [];

        foreach ($this->apiRoutes() as $route) {
            $signature = $this->signature($route);

            if ($signature === self::PUBLIC_ROUTE) {
                continue;
            }

            if (in_array(EnsurePasswordIsChanged::class, $route->excludedMiddleware(), true)) {
                $exempt[] = $signature;

                continue;
            }

            if (! in_array('password.changed', $route->gatherMiddleware(), true)) {
                $withoutCheck[] = $signature;
            }
        }

        $this->assertSame(
            [],
            $withoutCheck,
            "Rutas sin password.changed (una cuenta con clave temporal conservaría el acceso):
"
            . implode("
", $withoutCheck)
        );

        sort($exempt);
        $this->assertSame(self::PASSWORD_CHANGE_ROUTES, $exempt);
    }

    public function test_las_rutas_eximidas_del_cambio_de_contrasenia_siguen_exigiendo_cuenta_activa(): void
    {
        foreach (['GET api/auth/yo', 'POST api/auth/cambiar-contrasenia'] as $signature) {
            $route = collect($this->apiRoutes())->first(fn ($route) => $this->signature($route) === $signature);

            $this->assertNotNull($route, "No existe la ruta {$signature}.");
            $this->assertContains('account.active', $route->gatherMiddleware());
            $this->assertNotContains(EnsureAccountIsActive::class, $route->excludedMiddleware());
        }
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

    public function test_el_login_es_la_unica_ruta_publica_y_tiene_su_propio_limitador(): void
    {
        $public = [];

        foreach ($this->apiRoutes() as $route) {
            if (! in_array('auth:sanctum', $route->gatherMiddleware(), true)) {
                $public[] = $route;
            }
        }

        $this->assertCount(1, $public);
        $this->assertSame(self::PUBLIC_ROUTE, $this->signature($public[0]));
        $this->assertContains('throttle:login', $public[0]->gatherMiddleware());
        $this->assertContains('throttle:api', $public[0]->excludedMiddleware());
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

            if (strpos($pattern, 'estudiante-') !== false) {
                return 'estudiante-1';
            }

            return strpos($pattern, '{12}') !== false
                ? '00000000-0000-4000-8000-000000000001'
                : '1';
        }, $route->uri());
    }
}
