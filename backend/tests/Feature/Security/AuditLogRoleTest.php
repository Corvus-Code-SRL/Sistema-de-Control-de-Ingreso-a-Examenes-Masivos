<?php

namespace Tests\Feature\Security;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsAuditLogRows;
use Tests\Concerns\SeedsSecurityAccounts;
use Tests\TestCase;

/**
 * RNF-01: la bitácora muestra con qué rol se hizo cada operación, según el período de usuario_rol que
 * contiene su fecha. Lo hecho con un rol sigue constando con ese rol aunque la cuenta pase después a otro.
 */
class AuditLogRoleTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsAuditLogRows;
    use SeedsSecurityAccounts;

    private User $marcelo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareAuditLog();

        // Auxiliar del 11/11 al 13/11 a las 10:30 (UTC); desde entonces, Docente.
        $this->marcelo = $this->namedAccount('Marcelo', 'Quiroga', '10452');
        $this->period($this->marcelo, Role::AUXILIAR, '2026-11-11 08:00:00+00', '2026-11-13 10:30:00+00');
        $this->period($this->marcelo, Role::DOCENTE, '2026-11-13 10:30:00+00', null);
    }

    private function period(User $user, string $role, string $from, ?string $to): void
    {
        DB::table('usuario_rol')->insert([
            'id_usuario' => $user->id_usuario,
            'id_rol' => $this->roleId($role),
            'fecha_inicio' => $from,
            'fecha_fin' => $to,
        ]);
    }

    private function roleAt(string $at): ?string
    {
        $id = $this->logRow($this->marcelo, 'MODIFICAR', 'grupo', null, ['id_grupo' => 7], $at);

        return $this->getJson('/api/bitacora/' . $id)->assertOk()->json('data.usuario.rol');
    }

    public function test_lo_hecho_como_auxiliar_sigue_diciendo_auxiliar_despues_del_cambio(): void
    {
        $this->assertSame(Role::AUXILIAR, $this->roleAt('2026-11-12 15:00:00+00'));
        $this->assertSame(Role::DOCENTE, $this->roleAt('2026-11-14 09:00:00+00'));
    }

    public function test_el_instante_del_cambio_ya_pertenece_al_rol_nuevo(): void
    {
        $this->assertSame(Role::AUXILIAR, $this->roleAt('2026-11-13 10:29:59+00'));
        $this->assertSame(Role::DOCENTE, $this->roleAt('2026-11-13 10:30:00+00'));
    }

    public function test_antes_de_tener_rol_el_rol_queda_vacio(): void
    {
        $this->assertNull($this->roleAt('2026-11-10 12:00:00+00'));
    }

    public function test_el_listado_muestra_el_rol_de_cada_operacion(): void
    {
        $this->logRow($this->marcelo, 'CREAR', 'grupo', null, ['id_grupo' => 7], '2026-11-12 15:00:00+00');
        $this->logRow($this->marcelo, 'MODIFICAR', 'grupo', null, ['id_grupo' => 7], '2026-11-14 09:00:00+00');

        $this->getJson('/api/bitacora')
            ->assertOk()
            ->assertJsonPath('data.0.usuario.rol', Role::DOCENTE)
            ->assertJsonPath('data.1.usuario.rol', Role::AUXILIAR);
    }

    public function test_los_filtros_siguen_funcionando_con_el_rol_calculado(): void
    {
        $this->logRow($this->marcelo, 'MODIFICAR', 'grupo', null, ['id_grupo' => 7], '2026-11-12 15:00:00+00');

        $this->getJson('/api/bitacora?q=Quiroga&accion=MODIFICAR&modulo=grupos')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.usuario.rol', Role::AUXILIAR);
    }
}
