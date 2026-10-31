<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Database\Seeders\ActionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TestData\AccountTestDataSeeder;
use Database\Seeders\TestData\CatalogTestDataSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * RNF-02: una cuenta que se deshabilita con la sesión abierta pierde el acceso en TODA ruta
 * protegida (EnsureAccountIsActive), no solo en las que consultan el estado por su cuenta.
 */
class InactiveAccountSessionTest extends TestCase
{
    use DatabaseTransactions;

    private const TEACHER_SIS = '10452';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RoleSeeder::class,
            ActionSeeder::class,
            CatalogTestDataSeeder::class,
            AccountTestDataSeeder::class,
        ]);
    }

    /** Las rutas que seguían respondiendo 200 a una cuenta INACTIVA con token vigente. */
    public function protectedRoutes(): array
    {
        return [
            'usuario autenticado' => ['/api/user'],
            'materias' => ['/api/materias'],
            'periodos' => ['/api/periodos'],
            'examenes' => ['/api/examenes'],
            'formulario de examen' => ['/api/examenes/formulario'],
            'examenes del auxiliar' => ['/api/auxiliar/examenes'],
            'examenes de control de ingreso' => ['/api/control-ingreso/examenes'],
        ];
    }

    /** @dataProvider protectedRoutes */
    public function test_una_cuenta_activa_conserva_el_acceso(string $uri): void
    {
        $token = $this->login(self::TEACHER_SIS)->json('data.token');

        $this->asToken($token)->getJson($uri)->assertOk();
    }

    /** @dataProvider protectedRoutes */
    public function test_una_cuenta_inactiva_con_token_vigente_recibe_403_y_el_token_se_revoca(string $uri): void
    {
        $token = $this->login(self::TEACHER_SIS)->json('data.token');
        $this->asToken($token)->getJson($uri)->assertOk();

        $this->deactivate(self::TEACHER_SIS);

        $this->asToken($token)->getJson($uri)
            ->assertStatus(403)
            ->assertExactJson([
                'message' => 'Su cuenta está deshabilitada. Contacte al Administrador.',
                'motivo' => 'cuenta_inactiva',
            ]);

        $this->assertSame(0, DB::table('personal_access_tokens')->count());

        $this->asToken($token)->getJson($uri)->assertStatus(401);
    }

    public function test_el_rechazo_solo_revoca_el_token_usado_y_no_las_demas_sesiones_de_otra_cuenta(): void
    {
        $inactive = $this->login(self::TEACHER_SIS)->json('data.token');
        $other = $this->login('10487')->json('data.token');

        $this->deactivate(self::TEACHER_SIS);

        $this->asToken($inactive)->getJson('/api/materias')->assertStatus(403);

        $this->asToken($other)->getJson('/api/materias')->assertOk();
        $this->assertSame(1, DB::table('personal_access_tokens')->count());
    }

    public function test_el_login_de_una_cuenta_inactiva_sigue_rechazandose_con_el_mensaje_generico(): void
    {
        $this->deactivate(self::TEACHER_SIS);

        $this->login(self::TEACHER_SIS, 'incorrecta')->assertStatus(401)->assertExactJson([
            'message' => 'Código SIS o contraseña incorrectos.',
            'motivo' => 'credenciales_invalidas',
        ]);

        $this->login(self::TEACHER_SIS)->assertStatus(403)->assertJsonPath('motivo', 'cuenta_inactiva');
        $this->assertSame(0, DB::table('personal_access_tokens')->count());
    }

    public function test_logout_sigue_funcionando_para_una_cuenta_activa(): void
    {
        $token = $this->login(self::TEACHER_SIS)->json('data.token');

        $this->asToken($token)->postJson('/api/auth/logout')
            ->assertOk()
            ->assertJsonPath('data.sesion_cerrada', true);

        $this->assertSame(0, DB::table('personal_access_tokens')->count());
    }

    public function test_una_cuenta_inactiva_puede_cerrar_su_sesion(): void
    {
        $token = $this->login(self::TEACHER_SIS)->json('data.token');
        $this->deactivate(self::TEACHER_SIS);

        $this->asToken($token)->postJson('/api/auth/logout')
            ->assertOk()
            ->assertJsonPath('data.sesion_cerrada', true);

        $this->assertSame(0, DB::table('personal_access_tokens')->count());
        $this->asToken($token)->getJson('/api/materias')->assertStatus(401);
    }

    private function deactivate(string $codSis): void
    {
        DB::table('usuario')->where('cod_sis', $codSis)->update(['estado' => User::ESTADO_INACTIVO]);
    }

    private function login(string $codSis, string $password = 'password')
    {
        $this->app['auth']->forgetGuards();

        return $this->postJson('/api/auth/login', ['cod_sis' => $codSis, 'password' => $password]);
    }

    /** Cada petición con token resuelve la sesión desde cero, como en producción. */
    private function asToken(string $token): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }
}
