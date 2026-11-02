<?php

namespace Tests\Feature\Security;

use App\Models\Role;
use App\Models\User;
use App\Support\SystemAccounts;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\SeedsSecurityAccounts;
use Tests\TestCase;

/**
 * Regla general: una cuenta de sistema (config sciem.cuentas_sistema) firma la bitácora de las
 * automatizaciones y nunca inicia sesión, aunque tenga una contraseña conocida y un rol vigente.
 */
class SystemAccountsCannotLoginTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsSecurityAccounts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSecurityAccounts();
    }

    private function login(string $code, string $password = 'password')
    {
        $this->app['auth']->forgetGuards();

        return $this->postJson('/api/auth/login', ['cod_sis' => $code, 'password' => $password]);
    }

    private function systemAccount(string $code, string $role): User
    {
        $user = User::factory()->create([
            'cod_sis' => $code,
            'contrasenia' => Hash::make('password'),
        ]);
        $this->giveRole($user, $role);

        return $user;
    }

    public function test_una_cuenta_de_sistema_con_contrasenia_correcta_y_rol_administrador_no_ingresa(): void
    {
        $this->systemAccount('777000001', Role::ADMINISTRADOR);
        config(['sciem.cuentas_sistema' => ['777000001']]);

        $this->login('777000001')
            ->assertStatus(401)
            ->assertExactJson([
                'message' => 'Código SIS o contraseña incorrectos.',
                'motivo' => 'credenciales_invalidas',
            ]);

        $this->assertSame(0, DB::table('personal_access_tokens')->count());
    }

    public function test_el_codigo_de_reemplazo_tambien_queda_bloqueado_con_cualquier_rol(): void
    {
        config(['sciem.cuentas_sistema' => ['ADM00001']]);

        foreach ([Role::ADMINISTRADOR, Role::DOCENTE, Role::AUXILIAR] as $index => $role) {
            $code = $index === 0 ? 'ADM00001' : "ADM0000{$index}X";
            $this->systemAccount($code, $role);
        }

        $this->login('adm00001')->assertStatus(401);
        $this->assertSame(0, DB::table('personal_access_tokens')->count());
    }

    public function test_solo_se_bloquean_los_codigos_listados(): void
    {
        config(['sciem.cuentas_sistema' => ['777000001']]);
        $this->systemAccount('ADM0001', Role::ADMINISTRADOR);

        $this->login('ADM0001')->assertOk();
    }

    public function test_el_rechazo_de_una_cuenta_de_sistema_queda_en_la_bitacora(): void
    {
        $this->systemAccount('777000001', Role::ADMINISTRADOR);
        config(['sciem.cuentas_sistema' => ['777000001']]);

        $this->login('777000001')->assertStatus(401);

        $this->assertSame(1, DB::table('log')->where('nuevo_valor->motivo', 'cuenta_de_sistema')->count());
    }

    public function test_la_cuenta_de_sistema_de_la_configuracion_por_defecto_es_000000000(): void
    {
        $this->assertTrue(SystemAccounts::isSystemCode('000000000'));
        $this->assertFalse(SystemAccounts::isSystemCode('ADM0001'));
    }
}
