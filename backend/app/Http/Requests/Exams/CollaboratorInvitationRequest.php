<?php

namespace App\Http\Requests\Exams;

use App\Services\Security\UserRoleService;
use App\Support\CurrentUser;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Base de las peticiones para invitar docentes colaboradores (HU-026).
 *
 * Invita una cuenta activa cuyo rol ejerce la función invite_teachers (config sciem.access): hoy solo el
 * Docente. Un Invitado, un Auxiliar, un Administrador o una cuenta sin rol o inactiva reciben 403. Que el
 * examen sea del docente y esté PROGRAMADO lo decide CollaboratorInvitationService.
 */
abstract class CollaboratorInvitationRequest extends FormRequest
{
    public function authorize(CurrentUser $currentUser, UserRoleService $roles): bool
    {
        return $roles->canUse($currentUser->id(), 'invite_teachers');
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('Solo un docente puede invitar colaboradores a un examen.');
    }
}
