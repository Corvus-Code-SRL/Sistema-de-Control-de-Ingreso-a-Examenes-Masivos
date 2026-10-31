<?php

namespace App\Services\Security;

use App\Models\Role;
use App\Support\RecordStatus;
use Illuminate\Database\Eloquent\Collection;

/**
 * Catálogo de roles que el Administrador puede asignar.
 */
class RoleService
{
    public function listAssignableRoles(): Collection
    {
        return Role::query()
            ->where('estado', RecordStatus::ACTIVE)
            ->orderBy('id_rol')
            ->get();
    }
}
