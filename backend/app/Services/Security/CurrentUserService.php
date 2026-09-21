<?php

namespace App\Services\Security;

use App\Models\Role;
use App\Models\User;

/**
 * Resuelve quién ejecuta la operación y si es Administrador.
 *
 * Sin autenticación real, el usuario se toma de config('sciem.usuario_prueba'),
 * igual que lo hace AuditLogService para la bitácora.
 */
class CurrentUserService
{
    public function id(): ?string
    {
        return auth()->id() ?? config('sciem.usuario_prueba');
    }

    public function isAdministrator(): bool
    {
        $userId = $this->id();

        if ($userId === null) {
            return false;
        }

        return User::whereKey($userId)
            ->whereHas('activeRoles', fn ($query) => $query->where('nombre_rol', Role::ADMINISTRADOR))
            ->exists();
    }
}
