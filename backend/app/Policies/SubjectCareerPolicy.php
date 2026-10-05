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

    private function isAdministrator(User $user): bool
    {
        $activeRole = $user->rolActivo();

        return $activeRole !== null
            && $activeRole->nombre_rol === Role::ADMINISTRADOR;
    }
}