<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

class SubjectCareerPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isAdministrator($user);
    }

    public function create(User $user): bool
    {
        return $this->isAdministrator($user);
    }

    /** Una cuenta INACTIVA no ejerce el rol aunque conserve un token vigente. */
    private function isAdministrator(User $user): bool
    {
        $activeRole = $user->rolActivo();

        return $user->estado === User::ESTADO_ACTIVO
            && $activeRole !== null
            && $activeRole->nombre_rol === Role::ADMINISTRADOR;
    }
}