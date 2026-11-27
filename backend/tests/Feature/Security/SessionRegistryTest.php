<?php

namespace Tests\Feature\Security;

use App\Models\Session;
use App\Models\User;
use App\Services\Security\SessionRegistry;
use Database\Seeders\ActionSeeder;
use Database\Seeders\AdministratorAccountSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TestData\AccountTestDataSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Registro de sesiones: cada inicio de sesión abre una fila de `sesion` con la IP y el navegador del
 * dispositivo, y se cierra al salir (CIERRE), al vencer el token (EXPIRADA) o cuando el sistema la revoca
 * (REVOCADA). Cada apertura y cada cierre queda en la bitácora.
 */
class SessionRegistryTest extends TestCase
{
    use DatabaseTransactions;

    private const TEACHER_SIS = '10452';

    private const PHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Mobile/15E148';

    private const DESKTOP = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/130.0';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            UserSeeder::class,
            RoleSeeder::class,
            ActionSeeder::class,
            AdministratorAccountSeeder::class,
            AccountTestDataSeeder::class,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function login(string $agent = self::DESKTOP, string $codSis = self::TEACHER_SIS): string
    {
        $this->app['auth']->forgetGuards();

        return (string) $this->withHeaders(['User-Agent' => $agent])
            ->postJson('/api/auth/login', ['cod_sis' => $codSis, 'password' => 'password'])
            ->assertOk()
            ->json('data.token');
    }

    private function asToken(string $token): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    private function teacher(): User
    {
        return User::where('cod_sis', self::TEACHER_SIS)->firstOrFail();
    }

    /** @return array<int, array<string, mixed>> filas de la bitácora de una acción sobre `sesion` */
    private function audit(string $operation): array
    {
        return DB::table('log')
            ->join('accion', 'accion.id_accion', '=', 'log.id_accion')
            ->where('accion.operacion', $operation)
            ->where('log.tabla_afectada', 'sesion')
            ->orderBy('log.id_log')
            ->get(['log.id_usuario', 'log.antiguo_valor', 'log.nuevo_valor'])
            ->map(fn ($row) => [
                'id_usuario' => $row->id_usuario,
                'antes' => json_decode((string) $row->antiguo_valor, true),
                'despues' => json_decode((string) $row->nuevo_valor, true),
            ])
            ->all();
    }

    public function test_iniciar_sesion_abre_una_sesion_con_ip_y_navegador(): void
    {
        $this->login(self::PHONE);

        $session = Session::sole();
        $this->assertSame($this->teacher()->id_usuario, $session->id_usuario);
        $this->assertSame('127.0.0.1', $session->ip_direccion);
        $this->assertSame(self::PHONE, $session->agente);
        $this->assertSame((int) DB::table('personal_access_tokens')->value('id'), $session->id_token);
        $this->assertNull($session->hora_salida);

        $audit = $this->audit('INICIO_SESION');
        $this->assertCount(1, $audit);
        $this->assertSame($session->id_usuario, $audit[0]['id_usuario']);
        $this->assertSame($session->id_sesion, $audit[0]['despues']['id_sesion']);
        $this->assertSame(self::PHONE, $audit[0]['despues']['agente']);
    }

    public function test_cada_dispositivo_tiene_su_propia_sesion(): void
    {
        $this->login(self::PHONE);
        $this->login(self::DESKTOP);

        $this->assertSame(
            [self::PHONE, self::DESKTOP],
            Session::orderBy('id_sesion')->pluck('agente')->all()
        );
    }

    public function test_un_inicio_fallido_no_abre_sesion(): void
    {
        $this->postJson('/api/auth/login', ['cod_sis' => self::TEACHER_SIS, 'password' => 'otra'])->assertStatus(401);

        $this->assertSame(0, Session::count());
    }

    public function test_un_navegador_muy_largo_se_recorta(): void
    {
        $this->login(str_repeat('a', 400));

        $this->assertSame(255, strlen((string) Session::sole()->agente));
    }

    public function test_cerrar_sesion_la_marca_como_cierre_y_conserva_la_fila(): void
    {
        $token = $this->login();

        $this->asToken($token)->postJson('/api/auth/logout')->assertOk();

        $session = Session::sole();
        $this->assertSame(Session::CLOSED, $session->motivo_salida);
        $this->assertNotNull($session->hora_salida);
        // El token se borró: la sesión queda sin token, pero no se borra.
        $this->assertNull($session->id_token);

        $audit = $this->audit('CIERRE_SESION');
        $this->assertCount(1, $audit);
        $this->assertSame($session->id_usuario, $audit[0]['id_usuario']);
        $this->assertSame(Session::CLOSED, $audit[0]['despues']['motivo_salida']);
        $this->assertNull($audit[0]['antes']['hora_salida']);
    }

    public function test_cerrar_una_sesion_no_toca_la_del_otro_dispositivo(): void
    {
        $phone = $this->login(self::PHONE);
        $this->login(self::DESKTOP);

        $this->asToken($phone)->postJson('/api/auth/logout')->assertOk();

        $this->assertNull(Session::where('agente', self::DESKTOP)->value('hora_salida'));
    }

    public function test_cambiar_la_contrasenia_revoca_las_otras_sesiones(): void
    {
        $this->login(self::PHONE);
        $desktop = $this->login(self::DESKTOP);

        $this->asToken($desktop)->postJson('/api/auth/cambiar-contrasenia', [
            'contrasenia_actual' => 'password',
            'contrasenia_nueva' => 'ClaveNueva2026',
            'contrasenia_nueva_confirmation' => 'ClaveNueva2026',
        ])->assertOk();

        $this->assertSame(Session::REVOKED, Session::where('agente', self::PHONE)->value('motivo_salida'));
        $this->assertNull(Session::where('agente', self::DESKTOP)->value('hora_salida'));
        $this->assertSame($this->teacher()->id_usuario, $this->audit('CIERRE_SESION')[0]['id_usuario']);
        $this->assertSame(Session::REVOKED, $this->audit('CIERRE_SESION')[0]['despues']['motivo_salida']);
    }

    public function test_una_cuenta_deshabilitada_pierde_la_sesion_como_revocada(): void
    {
        $token = $this->login();
        $this->teacher()->forceFill(['estado' => 'INACTIVO'])->save();

        $this->asToken($token)->getJson('/api/auth/yo')->assertStatus(403);

        $this->assertSame(Session::REVOKED, Session::sole()->motivo_salida);
        // Todo cierre lo firma la dueña de la sesión; el motivo dice que fue revocada.
        $this->assertSame($this->teacher()->id_usuario, $this->audit('CIERRE_SESION')[0]['id_usuario']);
    }

    public function test_la_tarea_cierra_como_expirada_a_la_hora_del_vencimiento_y_borra_el_token(): void
    {
        Carbon::setTestNow('2026-11-26 08:00:00');
        $this->login(self::PHONE);

        Carbon::setTestNow('2026-11-26 19:59:00');
        $this->login(self::DESKTOP);

        Carbon::setTestNow('2026-11-26 21:30:00');
        $closed = app(SessionRegistry::class)->closeExpired();

        $this->assertSame(1, $closed);

        $phone = Session::where('agente', self::PHONE)->firstOrFail();
        $this->assertSame(Session::EXPIRED, $phone->motivo_salida);
        // 08:00 + 720 minutos, no la hora en que corrió la tarea.
        $this->assertTrue($phone->hora_salida->equalTo(Carbon::parse('2026-11-26 20:00:00')));
        $this->assertNull($phone->id_token);
        $this->assertSame(1, DB::table('personal_access_tokens')->count());

        $this->assertNull(Session::where('agente', self::DESKTOP)->value('hora_salida'));
        $this->assertSame($this->teacher()->id_usuario, $this->audit('CIERRE_SESION')[0]['id_usuario']);
        $this->assertSame(Session::EXPIRED, $this->audit('CIERRE_SESION')[0]['despues']['motivo_salida']);
    }

    public function test_la_tarea_es_idempotente(): void
    {
        Carbon::setTestNow('2026-11-26 08:00:00');
        $this->login();

        Carbon::setTestNow('2026-11-27 08:00:00');
        app(SessionRegistry::class)->closeExpired();
        $logs = DB::table('log')->count();

        $this->assertSame(0, app(SessionRegistry::class)->closeExpired());
        $this->assertSame($logs, DB::table('log')->count());
    }

    public function test_una_sesion_cuyo_token_ya_no_existe_tambien_se_cierra(): void
    {
        $this->login();
        DB::table('personal_access_tokens')->delete();

        $this->assertSame(1, app(SessionRegistry::class)->closeExpired());
        $this->assertSame(Session::EXPIRED, Session::sole()->motivo_salida);
    }

    public function test_la_bitacora_muestra_la_sesion_en_el_modulo_cuentas(): void
    {
        $this->login(self::PHONE);
        $admin = User::where('cod_sis', 'ADM0001')->firstOrFail();
        $this->app['auth']->forgetGuards();
        $this->actingAs($admin, 'sanctum');

        $id = (int) DB::table('log')
            ->join('accion', 'accion.id_accion', '=', 'log.id_accion')
            ->where('accion.operacion', 'INICIO_SESION')
            ->where('log.id_usuario', $this->teacher()->id_usuario)
            ->value('log.id_log');

        $this->getJson('/api/bitacora/' . $id)
            ->assertOk()
            ->assertJsonPath('data.accion.etiqueta', 'Inicio de sesión')
            ->assertJsonPath('data.modulo.clave', 'cuentas')
            ->assertJsonPath('data.registro.etiqueta', 'Sesión');
    }
}
