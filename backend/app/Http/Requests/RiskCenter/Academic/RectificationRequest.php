<?php

namespace App\Http\Requests\RiskCenter\Academic;

use App\Services\Security\UserRoleService;
use App\Support\CurrentUser;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Rectificación de la Central de Riesgo de estudiantes (HU-062): solo los roles de la función risk_rectify
 * (config sciem.access), el Administrador. Consultar la Central no concede este permiso.
 */
abstract class RectificationRequest extends FormRequest
{
    protected const MAX_ID = 2147483647;

    public function authorize(CurrentUser $currentUser, UserRoleService $roles): bool
    {
        return $roles->canUse($currentUser->id(), 'risk_rectify');
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('No tiene permiso para rectificar registros de la Central de Riesgo.');
    }

    public function rules(): array
    {
        return [];
    }
}
