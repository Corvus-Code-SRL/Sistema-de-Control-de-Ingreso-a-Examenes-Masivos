<?php

namespace App\Http\Requests\Academic;

use App\Services\Security\UserRoleService;
use App\Support\CurrentUser;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Base de las peticiones de gestión de auxiliares (HU-08).
 *
 * Quien gestiona es el docente: una cuenta con rol Auxiliar no puede, aunque esté en el
 * grupo o examen. Es el mismo patrón de HU-09 (se rechaza al Auxiliar, no se exige Docente).
 * Que el grupo o examen sea del docente lo decide AssistantService.
 */
abstract class AssistantManagementRequest extends FormRequest
{
    public function authorize(CurrentUser $currentUser, UserRoleService $roles): bool
    {
        return ! $roles->isAssistant($currentUser->id());
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('Un auxiliar no puede gestionar auxiliares.');
    }
}
