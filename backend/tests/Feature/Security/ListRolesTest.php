<?php

namespace Tests\Feature\Security;

use App\Models\Role;
use App\Models\User;
use App\Support\RecordStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsSecurityAccounts;
use Tests\TestCase;

class ListRolesTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsSecurityAccounts;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedSecurityAccounts();
    }

    public function test_lista_los_tres_roles_definidos_para_sciem(): void
    {
        $this->getJson('/api/roles')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.nombre_rol', Role::ADMINISTRADOR)
            ->assertJsonPath('data.1.nombre_rol', Role::DOCENTE)
            ->assertJsonPath('data.2.nombre_rol', Role::AUXILIAR);
    }

    public function test_no_ofrece_roles_inactivos(): void
    {
        DB::table('rol')->where('nombre_rol', Role::AUXILIAR)->update(['estado' => RecordStatus::INACTIVE]);

        $names = collect($this->getJson('/api/roles')->assertOk()->json('data'))->pluck('nombre_rol');

        $this->assertNotContains(Role::AUXILIAR, $names);
    }

    public function test_solo_un_administrador_puede_consultar_el_catalogo(): void
    {
        $this->actAs(User::factory()->create());

        $this->getJson('/api/roles')->assertForbidden();
    }
}
