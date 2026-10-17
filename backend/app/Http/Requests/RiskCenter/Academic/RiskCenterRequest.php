<?php

namespace App\Http\Requests\RiskCenter\Academic;

use App\Models\Role;
use App\Services\Security\UserRoleService;
use App\Support\CurrentUser;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Consulta de la Central de Riesgo de estudiantes: solo Docente y Administrador activos.
 *
 * El registro es institucional y de solo lectura; el Auxiliar no tiene acceso.
 */
abstract class RiskCenterRequest extends FormRequest
{
    protected const MAX_ID = 2147483647;

    public function authorize(CurrentUser $currentUser, UserRoleService $roles): bool
    {
        return $roles->hasAnyActiveRole($currentUser->id(), [Role::ADMINISTRADOR, Role::DOCENTE]);
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('No tiene permiso para consultar la Central de Riesgo.');
    }

    public function rules(): array
    {
        return [];
    }
}
