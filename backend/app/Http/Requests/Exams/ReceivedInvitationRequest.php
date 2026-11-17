<?php

namespace App\Http\Requests\Exams;

use App\Services\Security\UserRoleService;
use App\Support\CurrentUser;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Base de las peticiones sobre invitaciones recibidas (HU-027).
 *
 * Responde una cuenta activa cuyo rol ejerce la función teaching (config sciem.access): Docente o Invitado, los
 * mismos roles a los que HU-026 puede invitar. Que la invitación sea suya lo decide
 * ExamInvitationResponseService (404 si es de otro docente).
 */
abstract class ReceivedInvitationRequest extends FormRequest
{
    public function authorize(CurrentUser $currentUser, UserRoleService $roles): bool
    {
        return $roles->canUse($currentUser->id(), 'teaching');
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('Solo un docente puede consultar y responder invitaciones a exámenes.');
    }
}
