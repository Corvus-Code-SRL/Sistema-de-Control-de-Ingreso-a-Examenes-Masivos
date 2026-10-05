<?php

namespace App\Http\Requests\Academic;

use App\Services\Security\UserRoleService;
use App\Support\CurrentUser;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Base de las peticiones que registran o modifican grupos (HU-18, HU-19).
 *
 * Quien gestiona grupos es un docente activo: un Auxiliar, un Administrador o una cuenta sin
 * rol o inactiva reciben 403. Que el grupo sea del docente lo decide GroupPolicy; que el par
 * materia-carrera esté activo, GroupService.
 */
abstract class GroupManagementRequest extends FormRequest
{
    /** Máximo de un integer de PostgreSQL: por encima la consulta falla con 500. */
    protected const MAX_ID = 2147483647;

    public function authorize(CurrentUser $currentUser, UserRoleService $roles): bool
    {
        return $roles->isTeacher($currentUser->id());
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('Solo un docente puede gestionar grupos.');
    }
}
