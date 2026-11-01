<?php

namespace Tests\Feature\EntryControl;

use App\Models\User;
use Database\Seeders\ActionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TestData\AccountTestDataSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * HU-037 / RNF-03: la lista de solicitudes de ingreso con retraso se consulta en tiempo real, así que usa
 * la cuota del limitador `polling` y no la de `api`, igual que el estado del control de ingreso.
 */
class LateEntryPollingLimitTest extends TestCase
{
    use DatabaseTransactions;

    private const SHARED_IP = '203.0.113.10';
    private const AUTHENTICATED_ROUTE = '/api/user';
    private const REQUESTS_ROUTE = '/api/control-ingreso/examenes/1/retrasos/solicitudes';
    private const REQUESTS_ROUTE_NAME = 'control-ingreso.examenes.retrasos.solicitudes.index';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, ActionSeeder::class, AccountTestDataSeeder::class]);

        config([
            'sciem.rate_limits.api' => 3,
            'sciem.rate_limits.polling' => 2,
        ]);
    }

    public function test_la_ruta_usa_el_limitador_de_sondeo_en_lugar_del_de_acciones(): void
    {
        $middleware = Route::getRoutes()->getByName(self::REQUESTS_ROUTE_NAME)->gatherMiddleware();

        $this->assertContains('throttle:polling', $middleware);
        $this->assertNotContains('throttle:api', $middleware);
    }

    public function test_el_sondeo_de_solicitudes_se_limita_aparte_de_las_acciones(): void
    {
        $token = $this->tokenFor('10452');

        $this->assertNotSame(429, $this->hit(self::REQUESTS_ROUTE, $token, 2)->getStatusCode());
        $this->hit(self::REQUESTS_ROUTE, $token)->assertStatus(429);

        // Agotar el sondeo no le quita cuota a las acciones.
        $this->hit(self::AUTHENTICATED_ROUTE, $token, 3)->assertOk();
    }

    public function test_las_acciones_no_consumen_la_cuota_del_sondeo_de_solicitudes(): void
    {
        $token = $this->tokenFor('10452');

        $this->hit(self::AUTHENTICATED_ROUTE, $token, 3)->assertOk();
        $this->hit(self::AUTHENTICATED_ROUTE, $token)->assertStatus(429);

        $this->assertNotSame(429, $this->hit(self::REQUESTS_ROUTE, $token, 2)->getStatusCode());
    }

    private function tokenFor(string $codSis): string
    {
        return User::query()->where('cod_sis', $codSis)->firstOrFail()->createToken('prueba')->plainTextToken;
    }

    /** Repite la petición `$times` veces y devuelve la última respuesta. */
    private function hit(string $uri, string $token, int $times = 1)
    {
        $response = null;

        for ($attempt = 0; $attempt < $times; $attempt++) {
            // Cada petición debe resolver la sesión desde cero, como en producción.
            $this->app['auth']->forgetGuards();

            $response = $this->withServerVariables(['REMOTE_ADDR' => self::SHARED_IP])
                ->withHeaders(['Accept' => 'application/json'])
                ->withToken($token)
                ->getJson($uri);
        }

        return $response;
    }
}
