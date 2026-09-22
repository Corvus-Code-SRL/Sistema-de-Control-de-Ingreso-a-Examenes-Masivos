<?php

namespace Tests\Feature\Security;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\SeedsSecurityAccounts;
use Tests\TestCase;

class ShowUserTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsSecurityAccounts;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedSecurityAccounts();
    }

    public function test_muestra_la_cuenta_con_su_historial_de_roles_del_mas_reciente_al_mas_antiguo(): void
    {
        $account = User::factory()->create();

        $this->giveRole($account, Role::AUXILIAR, '2026-01-10 08:00:00');
        $this->postJson("/api/usuarios/{$account->id_usuario}/rol", ['id_rol' => $this->roleId(Role::DOCENTE)])
            ->assertOk();

        $this->getJson("/api/usuarios/{$account->id_usuario}")
            ->assertOk()
            ->assertJsonPath('data.id_usuario', $account->id_usuario)
            ->assertJsonPath('data.rol.nombre_rol', Role::DOCENTE)
            ->assertJsonCount(2, 'data.historial_roles')
            ->assertJsonPath('data.historial_roles.0.nombre_rol', Role::DOCENTE)
            ->assertJsonPath('data.historial_roles.0.fecha_fin', null)
            ->assertJsonPath('data.historial_roles.1.nombre_rol', Role::AUXILIAR)
            ->assertJsonPath('meta.id_usuario_actual', $this->administratorId);

        $this->assertNotNull(
            $this->getJson("/api/usuarios/{$account->id_usuario}")->json('data.historial_roles.1.fecha_fin')
        );
    }

    public function test_una_cuenta_sin_rol_tiene_el_historial_vacio(): void
    {
        $account = User::factory()->create();

        $this->getJson("/api/usuarios/{$account->id_usuario}")
            ->assertOk()
            ->assertJsonPath('data.rol', null)
            ->assertJsonCount(0, 'data.historial_roles');
    }

    public function test_responde_404_si_la_cuenta_no_existe_o_el_id_no_es_uuid(): void
    {
        $this->getJson('/api/usuarios/00000000-0000-4000-8000-000000000099')->assertNotFound();
        $this->getJson('/api/usuarios/1')->assertNotFound();
    }

    public function test_solo_un_administrador_puede_ver_el_detalle(): void
    {
        $account = User::factory()->create();
        $this->actAs($account);

        $this->getJson("/api/usuarios/{$account->id_usuario}")->assertForbidden();
    }
}
