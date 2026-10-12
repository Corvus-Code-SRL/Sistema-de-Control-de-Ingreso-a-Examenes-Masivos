<?php

namespace Tests\Feature\Security;

use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\ActionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TestData\AccountTestDataSeeder;
use Database\Seeders\TestData\CatalogTestDataSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * HU-001: una cuenta con contraseña temporal pendiente no usa ninguna ruta salvo /auth/yo,
 * /auth/logout y /auth/cambiar-contrasenia, y deja de estar bloqueada al cambiarla.
 */
class PasswordChangeTest extends TestCase
{
    use DatabaseTransactions;

    private const TEACHER_SIS = '10452';

    private const OTHER_SIS = '10487';

    private const NEW_PASSWORD = 'ClaveNueva2026';

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

    /** Una muestra de rutas protegidas de varios módulos. */
    public function protectedRoutes(): array
    {
        return [
            'usuario autenticado' => ['/api/user'],
            'materias' => ['/api/materias'],
            'periodos' => ['/api/periodos'],
            'examenes' => ['/api/examenes'],
            'examenes del auxiliar' => ['/api/auxiliar/examenes'],
            'examenes de control de ingreso' => ['/api/control-ingreso/examenes'],
        ];
    }

    /** @dataProvider protectedRoutes */
    public function test_con_el_cambio_pendiente_toda_ruta_protegida_responde_403(string $uri): void
    {
        $token = $this->loginAndRequireChange(self::TEACHER_SIS);

        $this->asToken($token)->getJson($uri)
            ->assertStatus(403)
            ->assertExactJson([
                'message' => 'Debe cambiar su contraseña temporal antes de continuar.',
                'motivo' => 'cambio_de_clave_pendiente',
            ]);
    }

    public function test_el_rechazo_no_revoca_el_token(): void
    {
        $token = $this->loginAndRequireChange(self::TEACHER_SIS);

        $this->asToken($token)->getJson('/api/materias')->assertStatus(403);
        $this->asToken($token)->getJson('/api/materias')->assertStatus(403);

        $this->assertSame(1, DB::table('personal_access_tokens')->count());
        $this->asToken($token)->getJson('/api/auth/yo')->assertOk();
    }

    public function test_la_confirmacion_de_contrasenia_tambien_queda_bloqueada(): void
    {
        $token = $this->loginAndRequireChange(self::TEACHER_SIS);

        $this->asToken($token)->postJson('/api/auth/confirmar-password', ['password' => 'password'])
            ->assertStatus(403)
            ->assertJsonPath('motivo', 'cambio_de_clave_pendiente');
    }

    public function test_yo_responde_y_avisa_que_el_cambio_esta_pendiente(): void
    {
        $token = $this->loginAndRequireChange(self::TEACHER_SIS);

        $this->asToken($token)->getJson('/api/auth/yo')
            ->assertOk()
            ->assertJsonPath('data.usuario.cod_sis', self::TEACHER_SIS)
            ->assertJsonPath('data.usuario.debe_cambiar_contrasenia', true);
    }

    public function test_el_login_informa_que_el_cambio_esta_pendiente(): void
    {
        $this->markChangePending(self::TEACHER_SIS);

        $this->login(self::TEACHER_SIS)
            ->assertOk()
            ->assertJsonPath('data.usuario.debe_cambiar_contrasenia', true);
    }

    public function test_logout_responde_con_el_cambio_pendiente(): void
    {
        $token = $this->loginAndRequireChange(self::TEACHER_SIS);

        $this->asToken($token)->postJson('/api/auth/logout')
            ->assertOk()
            ->assertJsonPath('data.sesion_cerrada', true);

        $this->assertSame(0, DB::table('personal_access_tokens')->count());
    }

    public function test_una_cuenta_con_el_indicador_apagado_no_se_ve_afectada(): void
    {
        $login = $this->login(self::TEACHER_SIS)
            ->assertOk()
            ->assertJsonPath('data.usuario.debe_cambiar_contrasenia', false);

        $token = $login->json('data.token');

        foreach ($this->protectedRoutes() as [$uri]) {
            $this->asToken($token)->getJson($uri)->assertOk();
        }

        $this->asToken($token)->getJson('/api/auth/yo')
            ->assertJsonPath('data.usuario.debe_cambiar_contrasenia', false);
    }

    public function test_una_cuenta_inactiva_conserva_su_motivo_aunque_tenga_el_cambio_pendiente(): void
    {
        $token = $this->loginAndRequireChange(self::TEACHER_SIS);
        DB::table('usuario')->where('cod_sis', self::TEACHER_SIS)->update(['estado' => User::ESTADO_INACTIVO]);

        $this->asToken($token)->getJson('/api/materias')
            ->assertStatus(403)
            ->assertJsonPath('motivo', 'cuenta_inactiva');
    }

    public function test_cambia_la_contrasenia_y_apaga_el_indicador(): void
    {
        $token = $this->loginAndRequireChange(self::TEACHER_SIS);

        $this->change($token, 'password', self::NEW_PASSWORD)
            ->assertOk()
            ->assertJsonPath('data.debe_cambiar_contrasenia', false)
            ->assertJsonPath('mensaje', 'Contraseña actualizada correctamente.');

        $account = User::where('cod_sis', self::TEACHER_SIS)->firstOrFail();

        $this->assertFalse($account->debe_cambiar_contrasenia);
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $account->contrasenia));
        $this->assertFalse(Hash::check('password', $account->contrasenia));
    }

    public function test_tras_el_cambio_las_rutas_protegidas_responden_con_el_mismo_token(): void
    {
        $token = $this->loginAndRequireChange(self::TEACHER_SIS);

        $this->change($token, 'password', self::NEW_PASSWORD)->assertOk();

        foreach ($this->protectedRoutes() as [$uri]) {
            $this->asToken($token)->getJson($uri)->assertOk();
        }

        $this->asToken($token)->getJson('/api/auth/yo')
            ->assertJsonPath('data.usuario.debe_cambiar_contrasenia', false);
    }

    public function test_despues_del_cambio_solo_entra_con_la_contrasenia_nueva(): void
    {
        $token = $this->loginAndRequireChange(self::TEACHER_SIS);

        $this->change($token, 'password', self::NEW_PASSWORD)->assertOk();

        $this->login(self::TEACHER_SIS, 'password')->assertStatus(401);
        $this->login(self::TEACHER_SIS, self::NEW_PASSWORD)
            ->assertOk()
            ->assertJsonPath('data.usuario.debe_cambiar_contrasenia', false);
    }

    public function test_el_cambio_revoca_las_demas_sesiones_y_conserva_la_actual(): void
    {
        $current = $this->loginAndRequireChange(self::TEACHER_SIS);
        $other = $this->login(self::TEACHER_SIS)->json('data.token');
        $someoneElse = $this->login(self::OTHER_SIS)->json('data.token');

        $this->change($current, 'password', self::NEW_PASSWORD)->assertOk();

        $this->asToken($other)->getJson('/api/materias')->assertStatus(401);
        $this->asToken($current)->getJson('/api/materias')->assertOk();
        $this->asToken($someoneElse)->getJson('/api/materias')->assertOk();
    }

    public function test_el_cambio_queda_en_la_bitacora_sin_valores_de_contrasenia(): void
    {
        $token = $this->loginAndRequireChange(self::TEACHER_SIS);
        $account = User::where('cod_sis', self::TEACHER_SIS)->firstOrFail();

        $this->change($token, 'password', self::NEW_PASSWORD)->assertOk();

        $entry = AuditLog::where('tabla_afectada', 'usuario')
            ->where('id_usuario', $account->id_usuario)
            ->whereHas('accion', fn ($query) => $query->where('operacion', 'MODIFICAR'))
            ->firstOrFail();

        $this->assertSame(['debe_cambiar_contrasenia' => true], $entry->antiguo_valor);
        $this->assertFalse($entry->nuevo_valor['debe_cambiar_contrasenia']);

        $serialised = json_encode([$entry->antiguo_valor, $entry->nuevo_valor]);
        $newHash = User::whereKey($account->id_usuario)->value('contrasenia');

        foreach (['password', self::NEW_PASSWORD, $newHash, $account->contrasenia] as $secret) {
            $this->assertStringNotContainsString($secret, str_replace('contrasenia_cambiada', '', $serialised));
        }
    }

    public function test_rechaza_la_contrasenia_actual_incorrecta(): void
    {
        $token = $this->loginAndRequireChange(self::TEACHER_SIS);

        $this->change($token, 'incorrecta', self::NEW_PASSWORD)
            ->assertStatus(422)
            ->assertJsonValidationErrors('contrasenia_actual');

        $this->assertStillPending();
    }

    public function test_rechaza_una_contrasenia_nueva_de_menos_de_8_caracteres(): void
    {
        $token = $this->loginAndRequireChange(self::TEACHER_SIS);

        $this->change($token, 'password', 'Corta1')
            ->assertStatus(422)
            ->assertJsonValidationErrors('contrasenia_nueva');

        $this->assertStillPending();
    }

    public function test_rechaza_una_confirmacion_que_no_coincide(): void
    {
        $token = $this->loginAndRequireChange(self::TEACHER_SIS);

        $this->asToken($token)->postJson('/api/auth/cambiar-contrasenia', [
            'contrasenia_actual' => 'password',
            'contrasenia_nueva' => self::NEW_PASSWORD,
            'contrasenia_nueva_confirmation' => 'Otra2026Clave',
        ])->assertStatus(422)->assertJsonValidationErrors('contrasenia_nueva');

        $this->assertStillPending();
    }

    public function test_rechaza_repetir_la_contrasenia_temporal(): void
    {
        $token = $this->loginAndRequireChange(self::TEACHER_SIS);

        $this->change($token, 'password', 'password')
            ->assertStatus(422)
            ->assertJsonValidationErrors('contrasenia_nueva');

        $this->assertStillPending();
    }

    public function test_exige_los_tres_campos(): void
    {
        $token = $this->loginAndRequireChange(self::TEACHER_SIS);

        $this->asToken($token)->postJson('/api/auth/cambiar-contrasenia', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['contrasenia_actual', 'contrasenia_nueva']);
    }

    public function test_sin_token_el_cambio_responde_401(): void
    {
        $this->actAsGuest();

        $this->postJson('/api/auth/cambiar-contrasenia', [
            'contrasenia_actual' => 'password',
            'contrasenia_nueva' => self::NEW_PASSWORD,
            'contrasenia_nueva_confirmation' => self::NEW_PASSWORD,
        ])->assertStatus(401);
    }

    public function test_limita_a_cinco_intentos_por_minuto_y_por_cuenta(): void
    {
        $token = $this->loginAndRequireChange(self::TEACHER_SIS);
        $other = $this->login(self::OTHER_SIS)->json('data.token');

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->change($token, 'incorrecta', self::NEW_PASSWORD)->assertStatus(422);
        }

        $this->change($token, 'password', self::NEW_PASSWORD)->assertStatus(429);
        $this->assertStillPending();

        // Otra cuenta tiene su propio contador.
        $this->change($other, 'incorrecta', self::NEW_PASSWORD)->assertStatus(422);
    }

    private function assertStillPending(): void
    {
        $account = User::where('cod_sis', self::TEACHER_SIS)->firstOrFail();

        $this->assertTrue($account->debe_cambiar_contrasenia);
        $this->assertTrue(Hash::check('password', $account->contrasenia));
    }

    private function markChangePending(string $codSis): void
    {
        DB::table('usuario')->where('cod_sis', $codSis)->update(['debe_cambiar_contrasenia' => true]);
    }

    /** Inicia sesión con una cuenta a la que se le marcó el cambio pendiente y devuelve su token. */
    private function loginAndRequireChange(string $codSis): string
    {
        $this->markChangePending($codSis);

        return $this->login($codSis)->assertOk()->json('data.token');
    }

    private function change(string $token, string $current, string $new)
    {
        return $this->asToken($token)->postJson('/api/auth/cambiar-contrasenia', [
            'contrasenia_actual' => $current,
            'contrasenia_nueva' => $new,
            'contrasenia_nueva_confirmation' => $new,
        ]);
    }

    private function asToken(string $token): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    private function login(string $codSis, string $password = 'password')
    {
        $this->app['auth']->forgetGuards();

        return $this->postJson('/api/auth/login', ['cod_sis' => $codSis, 'password' => $password]);
    }
}
