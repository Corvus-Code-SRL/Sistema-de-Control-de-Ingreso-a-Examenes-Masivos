<?php

namespace App\Services\Security;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Consulta de cuentas para su administración.
 *
 * El rol vigente se carga junto con las cuentas para no lanzar una consulta
 * por fila al serializarlas.
 */
class UserQueryService
{
    private CurrentUserService $currentUser;

    public function __construct(CurrentUserService $currentUser)
    {
        $this->currentUser = $currentUser;
    }

    public function listUsers(): Collection
    {
        return User::query()
            ->with('activeRoles')
            ->orderBy('apellido_paterno')
            ->orderBy('apellido_materno')
            ->orderBy('nombre')
            ->get();
    }

    /** El historial se ordena del rol más reciente al más antiguo. */
    public function findUser(User $user): User
    {
        return $user->load([
            'activeRoles',
            'roles' => fn ($query) => $query->orderByPivot('fecha_inicio', 'desc'),
        ]);
    }

    public function meta(): array
    {
        return ['id_usuario_actual' => $this->currentUser->id()];
    }
}
