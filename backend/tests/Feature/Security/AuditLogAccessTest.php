<?php

namespace Tests\Feature\Security;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\SeedsAuditLogRows;
use Tests\Concerns\SeedsSecurityAccounts;
use Tests\TestCase;

/**
 * HU-015: solo el Administrador consulta la bitácora (criterio 11), la consulta es de solo lectura (criterio 9)
 * y consultarla no deja rastro en `log`.
 */
class AuditLogAccessTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsAuditLogRows;
    use SeedsSecurityAccounts;

    private int $logId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareAuditLog();

        $this->logId = $this->logRow($this->admin, 'CREAR', 'ambiente', null, ['id_ambiente' => 1]);
    }

    /** @return array<int, string> */
    private function urls(): array
    {
        return ['/api/bitacora', '/api/bitacora/opciones', "/api/bitacora/{$this->logId}"];
    }

    public function test_el_administrador_activo_consulta_la_bitacora(): void
    {
        foreach ($this->urls() as $url) {
            $this->getJson($url)->assertOk();
        }
    }

    /** Criterio 11 */
    public function test_docente_invitado_y_auxiliar_reciben_403_en_toda_la_bitacora(): void
    {
        $guest = User::factory()->create(['cod_sis' => 'INV4821']);
        $this->giveRole($guest, Role::INVITADO);

        $accounts = [
            Role::DOCENTE => $this->createAccount(Role::DOCENTE),
            Role::INVITADO => $guest,
            Role::AUXILIAR => $this->createAccount(Role::AUXILIAR),
        ];

        foreach ($accounts as $role => $account) {
            $this->actAs($account);

            foreach ($this->urls() as $url) {
                $this->getJson($url)
                    ->assertForbidden()
                    ->assertJsonPath('message', 'No tiene permiso para ver esta sección.');
            }
        }
    }

    public function test_una_cuenta_sin_rol_recibe_403(): void
    {
        $this->actAs($this->createAccount());

        $this->getJson('/api/bitacora')->assertForbidden();
    }

    public function test_un_administrador_deshabilitado_con_token_vigente_no_consulta(): void
    {
        $inactive = $this->createAccount(Role::ADMINISTRADOR, User::ESTADO_INACTIVO);
        $this->actAs($inactive);

        $this->assertContains($this->getJson('/api/bitacora')->getStatusCode(), [401, 403]);
    }

    public function test_sin_sesion_responde_401(): void
    {
        $this->actAsGuest();

        foreach ($this->urls() as $url) {
            $this->getJson($url)->assertUnauthorized();
        }
    }

    public function test_el_docente_tampoco_ve_el_detalle_de_una_operacion_inexistente(): void
    {
        $this->actAs($this->createAccount(Role::DOCENTE));

        $this->getJson('/api/bitacora/999999')->assertForbidden();
    }

    public function test_solo_el_administrador_figura_en_la_funcion_de_acceso(): void
    {
        $this->assertSame([Role::ADMINISTRADOR], config('sciem.access.audit_log'));
    }

    /** Criterio 9 */
    public function test_las_rutas_de_la_bitacora_solo_aceptan_lectura(): void
    {
        $routes = array_values(array_filter(
            Route::getRoutes()->getRoutes(),
            fn (LaravelRoute $route) => strpos($route->uri(), 'api/bitacora') === 0
        ));

        $this->assertCount(3, $routes);

        foreach ($routes as $route) {
            $this->assertSame(['GET', 'HEAD'], $route->methods(), $route->uri());
        }
    }

    public function test_escribir_sobre_la_bitacora_no_es_posible_ni_para_el_administrador(): void
    {
        $before = DB::table('log')->orderBy('id_log')->get();

        foreach ($this->urls() as $url) {
            foreach (['postJson', 'putJson', 'patchJson', 'deleteJson'] as $method) {
                $status = $this->{$method}($url, ['id_accion' => 1])->getStatusCode();

                $this->assertContains($status, [404, 405], "{$method} {$url} respondió {$status}");
            }
        }

        $this->assertEquals($before, DB::table('log')->orderBy('id_log')->get());
    }

    public function test_consultar_la_bitacora_no_agrega_filas_al_log(): void
    {
        $count = DB::table('log')->count();

        foreach ($this->urls() as $url) {
            $this->getJson($url)->assertOk();
        }
        $this->getJson('/api/bitacora?q=a&accion=CREAR&modulo=ambientes&desde=2026-01-01&hasta=2026-12-31')->assertOk();

        $this->assertSame($count, DB::table('log')->count());
    }

    public function test_la_consulta_se_hace_bajo_el_limitador_api_y_no_el_de_polling(): void
    {
        $route = Route::getRoutes()->getByName('bitacora.index');
        $middleware = $this->app['router']->gatherRouteMiddleware($route);

        $this->assertContains(ThrottleRequests::class . ':api', $middleware);
        $this->assertNotContains(ThrottleRequests::class . ':polling', $middleware);
    }
}
