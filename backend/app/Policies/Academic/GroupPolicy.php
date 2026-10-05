<?php

namespace App\Policies\Academic;

use App\Models\Group;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Acceso al detalle de un grupo académico: solo lo abre el docente que lo dicta.
 *
 * El listado de grupos del par no pasa por aquí: sigue abierto a todos los docentes.
 */
class GroupPolicy
{
    /**
     * Sin autenticación real no hay usuario en sesión, así que el parámetro es opcional y el
     * Controller entrega el id del docente actuante: authorize('view', [$group, $teacherId]).
     */
    public function view(?User $user, Group $group, string $teacherId): Response
    {
        if ((string) $group->id_usuario_docente === $teacherId) {
            return Response::allow();
        }

        return Response::deny('Solo el docente que dicta el grupo puede ver su detalle.');
    }

    public function update(?User $user, Group $group, string $teacherId): Response
    {
        if ((string) $group->id_usuario_docente === $teacherId) {
            return Response::allow();
        }

        return Response::deny('Solo el docente que dicta el grupo puede modificarlo.');
    }
}
