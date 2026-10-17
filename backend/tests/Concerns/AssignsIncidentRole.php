<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Asigna el rol Docente o Auxiliar a un usuario de prueba. El trait SeedsRiskCenterRecords
 * inserta el usuario pero no le asigna rol; esto lo completa.
 */
trait AssignsIncidentRole
{
    protected function assignDocenteRole(string $userId): void
    {
        $this->assignRole($userId, 'Docente');
    }

    protected function assignAuxiliarRole(string $userId): void
    {
        $this->assignRole($userId, 'Auxiliar');
    }

    private function assignRole(string $userId, string $roleName): void
    {
        $roleId = DB::table('rol')->where('nombre_rol', $roleName)->value('id_rol');

        if ($roleId === null) {
            throw new RuntimeException("Falta el rol {$roleName}: ejecute RoleSeeder antes de los tests.");
        }

        $exists = DB::table('usuario_rol')
            ->where('id_usuario', $userId)
            ->where('id_rol', $roleId)
            ->whereNull('fecha_fin')
            ->exists();

        if (! $exists) {
            DB::table('usuario_rol')->insert([
                'id_usuario'   => $userId,
                'id_rol'       => $roleId,
                'fecha_inicio' => now(),
                'fecha_fin'    => null,
            ]);
        }
    }
}