<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

/** Las materias son de solo lectura desde la app: solo el Administrador consulta el catálogo. */
class SubjectPolicy
{
    public function viewAny(User $user): bool
    {
        $activeRole = $user->rolActivo();

        // Una cuenta INACTIVA no ejerce el rol aunque conserve un token vigente.
        return $user->estado === User::ESTADO_ACTIVO
            && $activeRole !== null
            && $activeRole->nombre_rol === Role::ADMINISTRADOR;
    }
}
