<?php

namespace App\Http\Requests\RiskCenter\Academic;

use App\Services\Security\UserRoleService;
use App\Support\CurrentUser;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Bandeja y respuestas de las solicitudes de rectificación (HU-062): solo los roles de la función
 * risk_rectify_confirm (config sciem.access). Que la solicitud sea de ese docente lo comprueba el Service.
 */
class RespondRectificationRequest extends FormRequest
{
    public function authorize(CurrentUser $currentUser, UserRoleService $roles): bool
    {
        return $roles->canUse($currentUser->id(), 'risk_rectify_confirm');
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('No tiene permiso para responder solicitudes de rectificación.');
    }

    public function rules(): array
    {
        return [];
    }
}
