<?php

namespace App\Http\Requests\Academic;

use App\Services\Security\UserRoleService;
use App\Support\CurrentUser;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Base de las peticiones de gestión de auxiliares (HU-08).
 *
 * Quien gestiona es un docente activo: un Auxiliar (aunque esté en el grupo o examen), un
 * Administrador o una cuenta sin rol o inactiva reciben 403. Que el grupo o examen sea del
 * docente lo decide AssistantService.
 */
abstract class AssistantManagementRequest extends FormRequest
{
    public function authorize(CurrentUser $currentUser, UserRoleService $roles): bool
    {
        return $roles->isTeacher($currentUser->id());
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('Solo un docente puede gestionar auxiliares.');
    }
}
