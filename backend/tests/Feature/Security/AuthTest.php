<?php

namespace Tests\Feature\Security;

use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\ActionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TestData\AccountTestDataSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Inicio de sesión con tokens de Sanctum (RNF-02, fase 1) sobre las cuentas de AccountTestDataSeeder,
 * todas con la contraseña "password".
 */
class AuthTest extends TestCase
{
    use DatabaseTransactions;

    private const TEACHER_ID = '00000000-0000-4000-8000-000000000011';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, ActionSeeder::class, AccountTestDataSeeder::class]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** @dataProvider accountsWithRole */
    public function test_inicia_sesion_y_devuelve_token_con_el_rol_vigente(string $codSis, string $role): void
    {
        $response = $this->login($codSis)
            ->assertOk()
            ->assertJsonPath('data.usuario.cod_sis', $codSis)
            ->assertJsonPath('data.rol.nombre_rol', $role)
            ->assertJsonPath('data.tipo_token', 'Bearer');

        $this->assertArrayNotHasKey('contrasenia', $response->json('data.usuario'));
        $this->assertArrayNotHasKey('password', $response->json('data.usuario'));
        $this->assertNotEmpty($response->json('data.token'));
        $this->assertNotNull($response->json('data.expira_en'));
    }

    public function accountsWithRole(): array
    {
        return [
            'administrador alfanumerico' => ['ADM0001', 'Administrador'],
            'docente de 5 digitos' => ['10452', 'Docente'],
            'auxiliar de 9 digitos' => ['201800451', 'Auxiliar'],
        ];
    }

    public function test_el_docente_con_historial_toma_el_rol_abierto_y_no_el_cerrado(): void
    {
        // 10452 fue Auxiliar (cerrado) y hoy es Docente.
        $this->login('10452')->assertOk()->assertJsonPath('data.rol.nombre_rol', 'Docente');
    }

    public function test_el_identificador_se_normaliza_al_iniciar_sesion(): void
    {
        $this->login('  adm0001 ')->assertOk()->assertJsonPath('data.usuario.cod_sis', 'ADM0001');
    }

    public function test_cuenta_sin_rol_vigente_responde_403_con_su_motivo(): void
    {
        $this->login('202000315')
            ->assertStatus(403)
            ->assertJsonPath('motivo', 'sin_rol_vigente')
            ->assertJsonPath('message', 'Su cuenta no tiene un rol vigente. Contacte al Administrador.');

        $this->assertSame(0, DB::table('personal_access_tokens')->count());
    }

    public function test_cuenta_inactiva_con_rol_cerrado_responde_403_con_su_motivo(): void
    {
        $this->login('10398')
            ->assertStatus(403)
            ->assertJsonPath('motivo', 'cuenta_inactiva')
            ->assertJsonPath('message', 'Su cuenta está deshabilitada. Contacte al Administrador.');

        $this->assertSame(0, DB::table('personal_access_tokens')->count());
    }

    public function test_contrasenia_incorrecta_responde_401_sin_revelar_que_la_cuenta_existe(): void
    {
        $this->login('10452', 'incorrecta')
            ->assertStatus(401)
            ->assertJsonPath('message', 'Código SIS o contraseña incorrectos.')
            ->assertJsonPath('motivo', 'credenciales_invalidas');
    }

    public function test_identificador_inexistente_responde_401_con_el_mismo_mensaje(): void
    {
        $wrongPassword = $this->login('10452', 'incorrecta');
        $unknownAccount = $this->login('99999', 'password');

        $unknownAccount->assertStatus(401);
        $this->assertSame($wrongPassword->getContent(), $unknownAccount->getContent());
    }

    public function test_el_estado_de_la_cuenta_no_se_revela_con_contrasenia_incorrecta(): void
    {
        // La cuenta inactiva con contraseña errónea es indistinguible de una inexistente.
        $this->login('10398', 'incorrecta')
            ->assertStatus(401)
            ->assertJsonPath('motivo', 'credenciales_invalidas');
    }

    public function test_exige_identificador_y_contrasenia_no_vacios(): void
    {
        $this->postJson('/api/auth/login', ['cod_sis' => '', 'password' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['cod_sis', 'password']);
    }

    public function test_cada_intento_fallido_de_una_cuenta_existente_queda_en_la_bitacora(): void
    {
        $this->login('10452', 'incorrecta')->assertStatus(401);
        $this->login('10398')->assertStatus(403);
        $this->login('202000315')->assertStatus(403);

        $entries = AuditLog::query()
            ->whereHas('accion', fn ($query) => $query->where('operacion', 'INICIO_SESION_FALLIDO'))
            ->orderBy('id_log')
            ->get();

        $this->assertCount(3, $entries);
        $this->assertSame(
            ['credenciales_invalidas', 'cuenta_inactiva', 'sin_rol_vigente'],
            $entries->map(fn ($entry) => $entry->nuevo_valor['motivo'])->all()
        );
        $this->assertSame('10452', $entries[0]->nuevo_valor['cod_sis']);
        $this->assertSame('127.0.0.1', $entries[0]->nuevo_valor['ip']);
        $this->assertSame(self::TEACHER_ID, $entries[0]->id_usuario);
        $this->assertNotNull($entries[0]->fecha_hora);
    }

    public function test_la_contrasenia_nunca_llega_a_la_bitacora(): void
    {
        $this->login('10452', 'incorrecta');

        $this->assertStringNotContainsString("incorrecta", (string) DB::table("log")->latest("id_log")->value("nuevo_valor"));
    }

    public function test_logout_revoca_el_token_y_el_token_revocado_responde_401(): void
    {
        $token = $this->login('10452')->json('data.token');

        $this->asToken($token)->getJson('/api/auth/yo')->assertOk();

        $this->asToken($token)->postJson('/api/auth/logout')
            ->assertOk()
            ->assertJsonPath('data.sesion_cerrada', true);

        $this->assertSame(0, DB::table('personal_access_tokens')->count());

        $this->asToken($token)->getJson('/api/auth/yo')->assertStatus(401);
    }

    public function test_logout_revoca_solo_el_token_en_uso(): void
    {
        $first = $this->login('10452')->json('data.token');
        $second = $this->login('10452')->json('data.token');

        $this->asToken($first)->postJson('/api/auth/logout')->assertOk();

        $this->assertSame(1, DB::table('personal_access_tokens')->count());
        $this->asToken($second)->getJson('/api/auth/yo')->assertOk();
    }

    public function test_yo_sin_token_responde_401(): void
    {
        $this->getJson('/api/auth/yo')->assertStatus(401);
    }

    public function test_yo_devuelve_cuenta_rol_y_navegacion_del_rol_vigente(): void
    {
        $this->seedNavigation();
        $token = $this->login('10452')->json('data.token');

        $response = $this->asToken($token)->getJson('/api/auth/yo')
            ->assertOk()
            ->assertJsonPath('data.usuario.id_usuario', self::TEACHER_ID)
            ->assertJsonPath('data.rol.nombre_rol', 'Docente')
            ->assertJsonPath('data.navegacion.permisos', ['examenes.gestionar'])
            ->assertJsonPath('data.navegacion.interfaces', ['Exámenes']);

        $this->assertNotNull($response->json('data.password_confirmado_en'));
    }

    public function test_yo_no_concede_navegacion_de_otro_rol_ni_de_filas_inactivas(): void
    {
        $this->seedNavigation();
        $token = $this->login('201800451')->json('data.token');

        $this->asToken($token)->getJson('/api/auth/yo')
            ->assertOk()
            ->assertJsonPath('data.rol.nombre_rol', 'Auxiliar')
            ->assertJsonPath('data.navegacion.permisos', [])
            ->assertJsonPath('data.navegacion.interfaces', []);
    }

    public function test_un_token_deja_de_servir_si_la_cuenta_pierde_su_rol(): void
    {
        $token = $this->login('201800451')->json('data.token');

        DB::table('usuario_rol')->where('id_usuario', User::where('cod_sis', '201800451')->value('id_usuario'))
            ->update(['fecha_fin' => now()]);

        $this->asToken($token)->getJson('/api/auth/yo')
            ->assertStatus(403)
            ->assertJsonPath('motivo', 'sin_rol_vigente');
    }

    public function test_el_token_vence_a_las_12_horas(): void
    {
        $this->assertSame(720, config('sanctum.expiration'));

        $token = $this->login('10452')->json('data.token');

        Carbon::setTestNow(now()->addMinutes(719));
        $this->asToken($token)->getJson('/api/auth/yo')->assertOk();

        Carbon::setTestNow(now()->addMinutes(2));
        $this->asToken($token)->getJson('/api/auth/yo')->assertStatus(401);
    }

    public function test_confirmar_password_renueva_el_momento_de_confirmacion_de_la_sesion(): void
    {
        $token = $this->login('10452')->json('data.token');
        $before = DB::table('personal_access_tokens')->value('password_confirmado_en');

        Carbon::setTestNow(now()->addMinutes(30));

        $this->asToken($token)->postJson('/api/auth/confirmar-password', ['password' => 'password'])
            ->assertOk()
            ->assertJsonStructure(['data' => ['password_confirmado_en']]);

        $this->assertNotNull($before);
        $this->assertGreaterThan($before, DB::table('personal_access_tokens')->value('password_confirmado_en'));
    }

    public function test_confirmar_password_con_contrasenia_incorrecta_responde_422_sin_cerrar_la_sesion(): void
    {
        $token = $this->login('10452')->json('data.token');

        $this->asToken($token)->postJson('/api/auth/confirmar-password', ['password' => 'incorrecta'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');

        $this->asToken($token)->getJson('/api/auth/yo')->assertOk();
    }

    public function test_confirmar_password_exige_token(): void
    {
        $this->postJson('/api/auth/confirmar-password', ['password' => 'password'])->assertStatus(401);
    }

    public function test_el_undecimo_intento_fallido_en_un_minuto_se_limita(): void
    {
        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->login('10452', 'incorrecta')->assertStatus(401);
        }

        $this->login('10452', 'incorrecta')->assertStatus(429);
    }

    public function test_el_429_trae_retry_after_con_los_segundos_que_faltan(): void
    {
        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->login('10452', 'incorrecta')->assertStatus(401);
        }

        $response = $this->login('10452', 'incorrecta')->assertStatus(429);

        $retryAfter = $response->headers->get('Retry-After');

        $this->assertNotNull($retryAfter);
        $this->assertGreaterThan(0, (int) $retryAfter);
        $this->assertLessThanOrEqual(60, (int) $retryAfter);
    }

    public function test_el_navegador_puede_leer_retry_after_entre_origenes(): void
    {
        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->login('10452', 'incorrecta');
        }

        $response = $this->withHeaders(['Origin' => 'http://localhost:5173'])
            ->postJson('/api/auth/login', ['cod_sis' => '10452', 'password' => 'incorrecta'])
            ->assertStatus(429);

        $this->assertStringContainsString(
            'Retry-After',
            (string) $response->headers->get('Access-Control-Expose-Headers')
        );
    }

    public function test_cada_rechazo_trae_su_codigo_de_motivo_ademas_del_mensaje(): void
    {
        $this->login('10452', 'incorrecta')->assertStatus(401)->assertExactJson([
            'message' => 'Código SIS o contraseña incorrectos.',
            'motivo' => 'credenciales_invalidas',
        ]);

        $this->login('10398')->assertStatus(403)->assertExactJson([
            'message' => 'Su cuenta está deshabilitada. Contacte al Administrador.',
            'motivo' => 'cuenta_inactiva',
        ]);

        $this->login('202000315')->assertStatus(403)->assertExactJson([
            'message' => 'Su cuenta no tiene un rol vigente. Contacte al Administrador.',
            'motivo' => 'sin_rol_vigente',
        ]);
    }

    public function test_las_rutas_que_antes_estaban_abiertas_ahora_exigen_token(): void
    {
        $this->getJson('/api/periodos')->assertStatus(401);
        $this->getJson('/api/materias')->assertStatus(401);

        $token = $this->login('10452')->json('data.token');

        $this->asToken($token)->getJson('/api/periodos')->assertOk();
        $this->asToken($token)->getJson('/api/materias')->assertOk();
    }

    /** Cada petición con token resuelve la sesión desde cero, como en producción. */
    private function asToken(string $token): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    private function login(string $codSis, string $password = 'password')
    {
        // Cada petición debe resolver la sesión desde cero, como en producción.
        $this->app['auth']->forgetGuards();

        return $this->postJson('/api/auth/login', ['cod_sis' => $codSis, 'password' => $password]);
    }

    /** Docente: una pantalla activa y otra inactiva; el Auxiliar solo tiene permisos inactivos. */
    private function seedNavigation(): void
    {
        $teacherRole = DB::table('rol')->where('nombre_rol', 'Docente')->value('id_rol');
        $assistantRole = DB::table('rol')->where('nombre_rol', 'Auxiliar')->value('id_rol');

        $permission = DB::table('permiso')->insertGetId(
            ['nombre_permiso' => 'examenes.gestionar', 'estado' => 'ACTIVO'],
            'id_permiso'
        );
        $inactivePermission = DB::table('permiso')->insertGetId(
            ['nombre_permiso' => 'ingreso.controlar', 'estado' => 'INACTIVO'],
            'id_permiso'
        );
        $ui = DB::table('ui')->insertGetId(['nombre_ui' => 'Exámenes', 'estado' => 'ACTIVO'], 'id_ui');
        $inactiveUi = DB::table('ui')->insertGetId(['nombre_ui' => 'Reportes', 'estado' => 'INACTIVO'], 'id_ui');

        DB::table('ui_permiso')->insert([
            ['id_ui' => $ui, 'id_permiso' => $permission, 'estado' => 'ACTIVO'],
            ['id_ui' => $inactiveUi, 'id_permiso' => $permission, 'estado' => 'ACTIVO'],
        ]);
        DB::table('permiso_rol')->insert([
            ['id_permiso' => $permission, 'id_rol' => $teacherRole, 'estado' => 'ACTIVO'],
            ['id_permiso' => $inactivePermission, 'id_rol' => $teacherRole, 'estado' => 'ACTIVO'],
            ['id_permiso' => $permission, 'id_rol' => $assistantRole, 'estado' => 'INACTIVO'],
        ]);
    }
}
