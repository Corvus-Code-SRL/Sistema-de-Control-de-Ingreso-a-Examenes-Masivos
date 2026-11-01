<?php

namespace Tests\Feature\Security;

use Database\Seeders\ActionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TestData\AccountTestDataSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * RNF-03: el login se limita por código SIS + IP (fuerza bruta sobre una cuenta) y por IP (rociado de cuentas),
 * sin que el NAT del campus convierta a todos los controladores en una sola cuenta.
 */
class LoginRateLimitTest extends TestCase
{
    use DatabaseTransactions;

    private const IP = '203.0.113.10';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, ActionSeeder::class, AccountTestDataSeeder::class]);
    }

    public function test_superar_el_limite_sobre_la_misma_cuenta_y_la_misma_ip_es_429(): void
    {
        for ($attempt = 1; $attempt <= $this->accountLimit(); $attempt++) {
            $this->login('10452', 'incorrecta')->assertStatus(401);
        }

        $response = $this->login('10452', 'incorrecta')->assertStatus(429);

        $this->assertGreaterThan(0, (int) $response->headers->get('Retry-After'));
        $this->assertLessThanOrEqual(60, (int) $response->headers->get('Retry-After'));
    }

    public function test_el_codigo_se_normaliza_para_contar_los_intentos(): void
    {
        for ($attempt = 1; $attempt <= $this->accountLimit(); $attempt++) {
            $this->login($attempt % 2 === 0 ? ' adm0001 ' : 'ADM0001', 'incorrecta')->assertStatus(401);
        }

        $this->login('adm0001', 'incorrecta')->assertStatus(429);
    }

    public function test_tantas_cuentas_distintas_como_el_limite_por_cuenta_pasan_desde_la_misma_ip(): void
    {
        for ($account = 1; $account <= $this->accountLimit(); $account++) {
            $this->login('9000' . $account, 'incorrecta')->assertStatus(401);
        }

        $this->login('10452')->assertOk();
    }

    public function test_agotar_una_cuenta_no_bloquea_a_la_misma_cuenta_desde_otra_ip(): void
    {
        for ($attempt = 1; $attempt <= $this->accountLimit(); $attempt++) {
            $this->login('10452', 'incorrecta');
        }

        $this->login('10452', 'incorrecta')->assertStatus(429);
        $this->login('10452', 'incorrecta', '203.0.113.99')->assertStatus(401);
    }

    public function test_el_limite_por_ip_se_dispara_al_excederse(): void
    {
        config(['sciem.rate_limits.login_ip' => 5]);

        for ($account = 1; $account <= 5; $account++) {
            $this->login('9000' . $account, 'incorrecta')->assertStatus(401);
        }

        $this->login('90006', 'incorrecta')->assertStatus(429);
        $this->login('90006', 'incorrecta', '203.0.113.99')->assertStatus(401);
    }

    public function test_el_login_no_comparte_la_cuota_del_limitador_api(): void
    {
        config(['sciem.rate_limits.guest' => 1]);

        for ($account = 1; $account <= 3; $account++) {
            $this->login('9000' . $account, 'incorrecta')->assertStatus(401);
        }
    }

    public function test_un_codigo_que_no_es_texto_no_rompe_el_limitador(): void
    {
        $this->postJson('/api/auth/login', ['cod_sis' => ['x'], 'password' => 'incorrecta'])->assertStatus(422);
    }

    private function accountLimit(): int
    {
        return config('sciem.rate_limits.login_account');
    }

    private function login(string $codSis, string $password = 'password', string $ip = self::IP)
    {
        // Cada petición debe resolver la sesión desde cero, como en producción.
        $this->app['auth']->forgetGuards();

        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/api/auth/login', ['cod_sis' => $codSis, 'password' => $password]);
    }
}
