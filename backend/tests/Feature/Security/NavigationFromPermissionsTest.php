<?php

namespace Tests\Feature\Security;

use App\Models\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsSecurityAccounts;
use Tests\TestCase;

/**
 * RNF-01: /yo devuelve en `navegacion` los permisos vigentes del rol y sus pantallas, tal como los dejó
 * RolePermissionSeeder. Un permiso cerrado (fecha_fin) ya no aparece.
 */
class NavigationFromPermissionsTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsSecurityAccounts;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedSecurityAccounts();
        DB::table('ui_permiso')->delete();
        DB::table('ui')->delete();
        DB::table('permiso_rol')->delete();
        DB::table('permiso')->delete();
        $this->seed(RolePermissionSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_el_administrador_recibe_sus_permisos_y_pantallas(): void
    {
        $this->actAs($this->createAccount(Role::ADMINISTRADOR));

        $navigation = $this->getJson('/api/auth/yo')->assertOk()->json('data.navegacion');

        $this->assertContains('audit_log', $navigation['permisos']);
        $this->assertContains('own_administrador', $navigation['permisos']);
        $this->assertContains('bitacora.consultar', $navigation['interfaces']);
        $this->assertContains('administracion.gestionar', $navigation['interfaces']);
        $this->assertNotContains('teaching', $navigation['permisos']);
    }

    public function test_el_auxiliar_recibe_sus_pantallas_propias(): void
    {
        $this->actAs($this->createAccount(Role::AUXILIAR));

        $navigation = $this->getJson('/api/auth/yo')->assertOk()->json('data.navegacion');

        $this->assertSame(['own_auxiliar'], $navigation['permisos']);
        $this->assertContains('asignaciones.consultar', $navigation['interfaces']);
    }

    public function test_un_permiso_cerrado_ya_no_aparece(): void
    {
        Carbon::setTestNow(now()->addMinute());
        config(['sciem.access.audit_log' => []]);
        $this->seed(RolePermissionSeeder::class);

        $this->actAs($this->createAccount(Role::ADMINISTRADOR));

        $navigation = $this->getJson('/api/auth/yo')->assertOk()->json('data.navegacion');

        $this->assertNotContains('audit_log', $navigation['permisos']);
        $this->assertNotContains('bitacora.consultar', $navigation['interfaces']);
    }
}
