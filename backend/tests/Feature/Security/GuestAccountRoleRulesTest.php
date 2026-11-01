<?php

namespace Tests\Feature\Security;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsSecurityAccounts;
use Tests\TestCase;

/**
 * Regla B.2: el rol de una cuenta de docente invitado (código INV) no se puede cambiar, y una cuenta
 * con código SIS nunca puede recibir el rol Invitado.
 */
class GuestAccountRoleRulesTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsSecurityAccounts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSecurityAccounts();
    }

    private function assign(User $user, string $role)
    {
        return $this->postJson("/api/usuarios/{$user->id_usuario}/rol", ['id_rol' => $this->roleId($role)]);
    }

    /** @dataProvider everyRole */
    public function test_el_rol_de_una_cuenta_inv_no_se_puede_cambiar(string $role): void
    {
        $guest = User::factory()->create(['cod_sis' => 'INV4821']);

        $this->assign($guest, $role)
            ->assertStatus(422)
            ->assertJsonPath('errors.id_rol.0', 'El rol de docente invitado no se puede cambiar.');

        $this->assertDatabaseMissing('usuario_rol', ['id_usuario' => $guest->id_usuario]);
    }

    public function test_una_cuenta_inv_con_rol_invitado_tampoco_lo_cambia(): void
    {
        $guest = User::factory()->create(['cod_sis' => 'INV4821']);
        $this->giveRole($guest, Role::INVITADO);

        foreach ([Role::INVITADO, Role::DOCENTE, Role::AUXILIAR, Role::ADMINISTRADOR] as $role) {
            $this->assign($guest, $role)
                ->assertStatus(422)
                ->assertJsonPath('errors.id_rol.0', 'El rol de docente invitado no se puede cambiar.');
        }

        $this->assertDatabaseHas('usuario_rol', [
            'id_usuario' => $guest->id_usuario,
            'id_rol' => $this->roleId(Role::INVITADO),
            'fecha_fin' => null,
        ]);
        $this->assertSame(1, DB::table('usuario_rol')->where('id_usuario', $guest->id_usuario)->count());
    }

    public function test_el_codigo_inv_se_reconoce_aunque_este_en_minusculas(): void
    {
        $guest = User::factory()->create(['cod_sis' => 'inv4822']);

        $this->assign($guest, Role::DOCENTE)->assertStatus(422);
    }

    public function everyRole(): array
    {
        return [[Role::ADMINISTRADOR], [Role::DOCENTE], [Role::AUXILIAR], [Role::INVITADO]];
    }

    public function test_una_cuenta_con_codigo_sis_nunca_recibe_el_rol_invitado(): void
    {
        $account = User::factory()->create(['cod_sis' => '10452']);
        $this->giveRole($account, Role::DOCENTE);

        $this->assign($account, Role::INVITADO)
            ->assertStatus(422)
            ->assertJsonPath(
                'errors.id_rol.0',
                'El rol Docente invitado es solo para cuentas de docente invitado, sin código SIS.'
            );

        $this->assertDatabaseMissing('usuario_rol', [
            'id_usuario' => $account->id_usuario,
            'id_rol' => $this->roleId(Role::INVITADO),
        ]);
    }

    public function test_una_cuenta_sin_rol_con_codigo_sis_tampoco_recibe_invitado(): void
    {
        $account = User::factory()->create(['cod_sis' => '202312399']);

        $this->assign($account, Role::INVITADO)->assertStatus(422);
    }

    public function test_las_cuentas_con_codigo_sis_siguen_cambiando_entre_los_roles_de_siempre(): void
    {
        $account = User::factory()->create(['cod_sis' => '10452']);

        $this->assign($account, Role::AUXILIAR)->assertOk();
        $this->assign($account, Role::DOCENTE)->assertOk();
    }
}
