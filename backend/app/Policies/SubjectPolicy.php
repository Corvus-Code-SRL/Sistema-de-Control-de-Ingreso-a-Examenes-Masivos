<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\Subject;
use App\Models\User;

class SubjectPolicy
{
    public function create(User $user): bool
    {
        $activeRole = $user->rolActivo();

        return $activeRole !== null
            && $activeRole->nombre_rol === Role::ADMINISTRADOR;
    }

    public function update(User $user, Subject $subject): bool
    {
        $activeRole = $user->rolActivo();

        return $activeRole !== null
            && $activeRole->nombre_rol === Role::ADMINISTRADOR;
    }
}