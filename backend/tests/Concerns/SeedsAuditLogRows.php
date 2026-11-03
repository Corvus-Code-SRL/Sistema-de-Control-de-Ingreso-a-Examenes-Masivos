<?php

namespace Tests\Concerns;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Escenario de la bitácora (HU-015): un Administrador que consulta y filas de `log` escritas directo, con la
 * fecha exacta que cada prueba necesita. Requiere SeedsSecurityAccounts.
 */
trait SeedsAuditLogRows
{
    protected User $admin;

    /** Siembra los catálogos, deja `log` vacío y actúa como un Administrador distinto de la cuenta de sistema. */
    protected function prepareAuditLog(): void
    {
        $this->seedSecurityAccounts();
        DB::table('log')->delete();

        $this->admin = $this->createAccount(Role::ADMINISTRADOR);
        $this->actAs($this->admin);
    }

    protected function namedAccount(string $name, string $paternal, string $code, ?string $role = null): User
    {
        $user = User::factory()->create([
            'nombre' => $name,
            'apellido_paterno' => $paternal,
            'apellido_materno' => null,
            'cod_sis' => $code,
        ]);

        if ($role !== null) {
            $this->giveRole($user, $role);
        }

        return $user;
    }

    /**
     * Inserta una fila de `log` y devuelve su id. `$at` lleva desfase explícito («2026-10-01 04:00:00+00»);
     * sin él, la fila queda con la hora actual.
     *
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    protected function logRow(
        User $actor,
        string $operation,
        string $table,
        ?array $before = null,
        ?array $after = null,
        ?string $at = null
    ): int {
        return (int) DB::table('log')->insertGetId([
            'id_accion' => DB::table('accion')->where('operacion', $operation)->value('id_accion'),
            'antiguo_valor' => $before === null ? null : json_encode($before),
            'nuevo_valor' => $after === null ? null : json_encode($after),
            'fecha_hora' => $at ?? now()->format('Y-m-d H:i:sP'),
            'tabla_afectada' => $table,
            'id_usuario' => $actor->id_usuario,
        ], 'id_log');
    }
}
