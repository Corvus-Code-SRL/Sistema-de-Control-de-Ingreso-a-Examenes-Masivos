<?php

namespace Tests\Feature\Security;

use App\Models\Session;
use App\Models\User;
use App\Services\Security\AuditLogService;
use Database\Seeders\ActionSeeder;
use Database\Seeders\AdministratorAccountSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TestData\AccountTestDataSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Sesión en cada operación de la bitácora (forma 2): cada fila de log escrita durante una petición con
 * token propio queda unida a la sesión (el dispositivo) desde la que se hizo. La sesión se guarda en
 * la columna `log.id_sesion`, con clave foránea compuesta (id_sesion, id_usuario) hacia `sesion`.
 */
class AuditLogSessionTest extends TestCase
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

    private function login(string $agent): string
    {
        $this->app['auth']->forgetGuards();

        return (string) $this->withHeaders(['User-Agent' => $agent])
            ->postJson('/api/auth/login', ['cod_sis' => self::TEACHER_SIS, 'password' => 'password'])
            ->assertOk()
            ->json('data.token');
    }

    private function changePassword(string $token): void
    {
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->postJson('/api/auth/cambiar-contrasenia', [
            'contrasenia_actual' => 'password',
            'contrasenia_nueva' => 'ClaveNueva2026',
            'contrasenia_nueva_confirmation' => 'ClaveNueva2026',
        ])->assertOk();
    }

    private function sessionOf(string $agent): int
    {
        return (int) Session::where('agente', $agent)->value('id_sesion');
    }

    private function lastLog(string $operation, string $table): int
    {
        return (int) DB::table('log')
            ->join('accion', 'accion.id_accion', '=', 'log.id_accion')
            ->where('accion.operacion', $operation)
            ->where('log.tabla_afectada', $table)
            ->max('log.id_log');
    }

    private function asAdministrator(): void
    {
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs(User::where('cod_sis', 'ADM0001')->firstOrFail());
    }

    /** Sesión guardada en la columna de la fila. */
    private function storedSession(int $logId): ?int
    {
        $value = DB::table('log')->where('id_log', $logId)->value('id_sesion');

        return $value === null ? null : (int) $value;
    }

    public function test_todo_lo_hecho_desde_una_sesion_se_encuentra_por_la_columna(): void
    {
        $desktop = $this->login(self::DESKTOP);
        $this->changePassword($desktop);

        $operations = DB::table('log')
            ->join('accion', 'accion.id_accion', '=', 'log.id_accion')
            ->where('log.id_sesion', $this->sessionOf(self::DESKTOP))
            ->orderBy('log.id_log')
            ->pluck('accion.operacion')
            ->all();

        $this->assertSame(['INICIO_SESION', 'MODIFICAR'], $operations);
    }

    public function test_el_json_de_la_fila_no_cambia(): void
    {
        $desktop = $this->login(self::DESKTOP);
        $this->changePassword($desktop);

        $after = json_decode((string) DB::table('log')->where('id_log', $this->lastLog('MODIFICAR', 'usuario'))
            ->value('nuevo_valor'), true);

        // jsonb no conserva el orden de las claves: se compara sin orden.
        $this->assertEquals(['debe_cambiar_contrasenia' => false, 'contrasenia_cambiada' => true], $after);
    }

    public function test_la_base_rechaza_una_sesion_de_otra_cuenta(): void
    {
        $this->login(self::DESKTOP);
        $other = User::where('cod_sis', 'ADM0001')->firstOrFail();

        $this->expectException(QueryException::class);

        $session = $this->sessionOf(self::DESKTOP);

        app(AuditLogService::class)
            ->registrar('MODIFICAR', 'grupo', null, ['num_grupo' => 2], $other->id_usuario, $session);
    }

    public function test_la_base_rechaza_una_sesion_que_no_existe(): void
    {
        $user = User::where('cod_sis', self::TEACHER_SIS)->firstOrFail();

        $this->expectException(QueryException::class);

        app(AuditLogService::class)
            ->registrar('MODIFICAR', 'grupo', null, ['num_grupo' => 2], $user->id_usuario, 999999);
    }

    public function test_la_operacion_queda_unida_a_la_sesion_del_dispositivo_que_la_hizo(): void
    {
        $this->login(self::PHONE);
        $desktop = $this->login(self::DESKTOP);

        $this->changePassword($desktop);

        $logId = $this->lastLog('MODIFICAR', 'usuario');
        $this->assertSame($this->sessionOf(self::DESKTOP), $this->storedSession($logId));
    }

    public function test_el_inicio_y_el_cierre_quedan_unidos_a_su_sesion(): void
    {
        $token = $this->login(self::PHONE);
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->postJson('/api/auth/logout')->assertOk();

        $session = $this->sessionOf(self::PHONE);
        $this->assertSame($session, $this->storedSession($this->lastLog('INICIO_SESION', 'sesion')));
        $this->assertSame($session, $this->storedSession($this->lastLog('CIERRE_SESION', 'sesion')));
    }

    public function test_la_revocacion_por_cambio_de_contrasenia_queda_en_la_sesion_que_hizo_el_cambio(): void
    {
        $this->login(self::PHONE);
        $desktop = $this->login(self::DESKTOP);

        $this->changePassword($desktop);

        $logId = $this->lastLog('CIERRE_SESION', 'sesion');
        $this->assertSame($this->sessionOf(self::DESKTOP), $this->storedSession($logId));
    }

    public function test_sin_token_propio_la_operacion_no_tiene_sesion(): void
    {
        $user = User::where('cod_sis', self::TEACHER_SIS)->firstOrFail();
        Sanctum::actingAs($user);

        app(AuditLogService::class)
            ->registrar('MODIFICAR', 'grupo', ['num_grupo' => 1], ['num_grupo' => 2], $user->id_usuario);

        $this->assertNull($this->storedSession($this->lastLog('MODIFICAR', 'grupo')));
    }

    public function test_el_detalle_muestra_el_dispositivo_y_no_lista_la_sesion_como_cambio(): void
    {
        $desktop = $this->login(self::DESKTOP);
        $this->changePassword($desktop);
        $id = $this->lastLog('MODIFICAR', 'usuario');

        $this->asAdministrator();
        $response = $this->getJson('/api/bitacora/' . $id)->assertOk();

        $response->assertJsonPath('data.id_sesion', $this->sessionOf(self::DESKTOP))
            ->assertJsonPath('data.sesion.agente', self::DESKTOP)
            ->assertJsonPath('data.sesion.ip_direccion', '127.0.0.1');
        $this->assertNotContains('_sesion', collect($response->json('data.cambios'))->pluck('campo')->all());
        $this->assertNotContains('id_sesion', collect($response->json('data.cambios'))->pluck('campo')->all());
    }

    public function test_una_baja_con_sesion_sigue_mostrando_los_campos_eliminados(): void
    {
        $this->login(self::DESKTOP);
        $user = User::where('cod_sis', self::TEACHER_SIS)->firstOrFail();

        app(AuditLogService::class)->registrar(
            'MODIFICAR',
            'grupo',
            ['num_grupo' => 3],
            null,
            $user->id_usuario,
            $this->sessionOf(self::DESKTOP)
        );
        $id = $this->lastLog('MODIFICAR', 'grupo');

        $this->asAdministrator();
        $changes = $this->getJson('/api/bitacora/' . $id)->assertOk()->json('data.cambios');

        $this->assertSame([['campo' => 'num_grupo', 'etiqueta' => 'Número de grupo', 'oculto' => false,
            'anterior' => 3, 'nuevo' => null]], $changes);
    }

    public function test_el_listado_trae_la_sesion_de_cada_operacion(): void
    {
        $desktop = $this->login(self::DESKTOP);
        $this->changePassword($desktop);

        $this->asAdministrator();
        $rows = collect($this->getJson('/api/bitacora?accion=MODIFICAR')->assertOk()->json('data'));

        $this->assertSame($this->sessionOf(self::DESKTOP), $rows->first()['id_sesion']);
    }
}
