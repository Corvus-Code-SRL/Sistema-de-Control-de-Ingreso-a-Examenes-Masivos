<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Database\Seeders\ActionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TestData\AccountTestDataSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * RNF-03: el limitador `api` cuenta por usuario (no por IP) y las consultas periódicas tienen cuota aparte.
 */
class ApiRateLimitTest extends TestCase
{
    use DatabaseTransactions;

    private const SHARED_IP = '203.0.113.10';
    private const AUTHENTICATED_ROUTE = '/api/user';
    private const POLLING_ROUTES = ['/api/control-ingreso/examenes/1/estado', '/api/examenes/1/en-curso'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, ActionSeeder::class, AccountTestDataSeeder::class]);

        config([
            'sciem.rate_limits.api' => 3,
            'sciem.rate_limits.polling' => 2,
            'sciem.rate_limits.guest' => 2,
        ]);
    }

    public function test_dos_usuarios_con_la_misma_ip_no_comparten_el_limite(): void
    {
        $teacher = $this->tokenFor('10452');
        $assistant = $this->tokenFor('201800451');

        $this->hit(self::AUTHENTICATED_ROUTE, $teacher, 3)->assertOk();
        $this->hit(self::AUTHENTICATED_ROUTE, $teacher)->assertStatus(429);

        $this->hit(self::AUTHENTICATED_ROUTE, $assistant, 3)->assertOk();
        $this->hit(self::AUTHENTICATED_ROUTE, $assistant)->assertStatus(429);
    }

    public function test_el_usuario_que_excede_el_limite_recibe_429_con_retry_after(): void
    {
        $token = $this->tokenFor('10452');

        $this->hit(self::AUTHENTICATED_ROUTE, $token, 3)->assertOk();

        $retryAfter = $this->hit(self::AUTHENTICATED_ROUTE, $token)
            ->assertStatus(429)
            ->headers->get('Retry-After');

        $this->assertGreaterThan(0, (int) $retryAfter);
        $this->assertLessThanOrEqual(60, (int) $retryAfter);
    }

    public function test_el_mismo_usuario_desde_otra_ip_comparte_su_limite(): void
    {
        $token = $this->tokenFor('10452');

        $this->hit(self::AUTHENTICATED_ROUTE, $token, 3, '203.0.113.1')->assertOk();

        $this->hit(self::AUTHENTICATED_ROUTE, $token, 1, '203.0.113.2')->assertStatus(429);
    }

    public function test_sin_sesion_se_limita_por_ip(): void
    {
        Route::middleware('api')->get('/api/prueba-limite-publica', fn () => 'ok');

        $this->hit('/api/prueba-limite-publica', null, 2, '203.0.113.1')->assertOk();
        $this->hit('/api/prueba-limite-publica', null, 1, '203.0.113.1')->assertStatus(429);

        $this->hit('/api/prueba-limite-publica', null, 1, '203.0.113.2')->assertOk();
    }

    public function test_un_token_invalido_se_limita_por_ip_en_una_ruta_publica(): void
    {
        Route::middleware('api')->get('/api/prueba-limite-publica', fn () => 'ok');

        $this->hit('/api/prueba-limite-publica', 'token-inexistente', 2, '203.0.113.1')->assertOk();
        $this->hit('/api/prueba-limite-publica', 'token-inexistente', 1, '203.0.113.1')->assertStatus(429);
    }

    /** @dataProvider pollingRoutes */
    public function test_el_polling_se_limita_aparte_de_las_acciones(string $pollingRoute): void
    {
        $token = $this->tokenFor('10452');

        $this->assertNotSame(429, $this->hit($pollingRoute, $token, 2)->getStatusCode());
        $this->hit($pollingRoute, $token)->assertStatus(429);

        // Agotar el polling no le quita cuota a las acciones.
        $this->hit(self::AUTHENTICATED_ROUTE, $token, 3)->assertOk();
    }

    /** @dataProvider pollingRoutes */
    public function test_las_acciones_no_consumen_la_cuota_del_polling(string $pollingRoute): void
    {
        $token = $this->tokenFor('10452');

        $this->hit(self::AUTHENTICATED_ROUTE, $token, 3)->assertOk();
        $this->hit(self::AUTHENTICATED_ROUTE, $token)->assertStatus(429);

        $this->assertNotSame(429, $this->hit($pollingRoute, $token, 2)->getStatusCode());
    }

    public function test_el_polling_de_dos_usuarios_con_la_misma_ip_no_se_comparte(): void
    {
        $teacher = $this->tokenFor('10452');
        $assistant = $this->tokenFor('201800451');
        $route = self::POLLING_ROUTES[0];

        $this->hit($route, $teacher, 3);

        $this->assertNotSame(429, $this->hit($route, $assistant, 2)->getStatusCode());
    }

    public function pollingRoutes(): array
    {
        return [
            'estado del control de ingreso' => [self::POLLING_ROUTES[0]],
            'examen en curso' => [self::POLLING_ROUTES[1]],
        ];
    }

    private function tokenFor(string $codSis): string
    {
        return User::query()->where('cod_sis', $codSis)->firstOrFail()->createToken('prueba')->plainTextToken;
    }

    /** Repite la petición `$times` veces y devuelve la última respuesta. */
    private function hit(string $uri, ?string $token, int $times = 1, string $ip = self::SHARED_IP)
    {
        $response = null;

        for ($attempt = 0; $attempt < $times; $attempt++) {
            // Cada petición debe resolver la sesión desde cero, como en producción.
            $this->app['auth']->forgetGuards();

            $request = $this->withServerVariables(['REMOTE_ADDR' => $ip])
                ->withHeaders(['Accept' => 'application/json']);

            if ($token !== null) {
                $request = $request->withToken($token);
            }

            $response = $request->getJson($uri);
        }

        return $response;
    }
}
