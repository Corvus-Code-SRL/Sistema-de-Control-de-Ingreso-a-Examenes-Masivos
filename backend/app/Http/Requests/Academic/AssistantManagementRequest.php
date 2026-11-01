<?php

namespace App\Http\Requests\Academic;

use App\Services\Security\UserRoleService;
use App\Support\CurrentUser;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Base de las peticiones de gestión de auxiliares (HU-08).
 *
 * Quien gestiona es una cuenta activa cuyo rol ejerce la función (config sciem.access): por
 * defecto exam_assistants (habilitar auxiliares en un examen) y, en las peticiones de auxiliares
 * de grupo, group_assistants. Un Auxiliar (aunque esté en el grupo o examen), un Administrador o
 * una cuenta sin rol o inactiva reciben 403. Que el grupo o examen sea del docente lo decide
 * AssistantService.
 */
abstract class AssistantManagementRequest extends FormRequest
{
    public function authorize(CurrentUser $currentUser, UserRoleService $roles): bool
    {
        return $roles->canUse($currentUser->id(), $this->feature());
    }

    protected function feature(): string
    {
        return 'exam_assistants';
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('Solo un docente puede gestionar auxiliares.');
    }
}
