<?php

namespace App\Http\Requests\Reports\Security;

use App\Http\Requests\Security\ListAuditLogRequest;
use App\Models\Role;
use App\Services\Security\UserRoleService;
use App\Support\CurrentUser;

/**
 * HU-068: validacion de filtros del reporte de bitacora.
 * El rango de fechas es obligatorio y el acceso es exclusivo
 * del administrador.
 */
class AuditLogReportRequest extends ListAuditLogRequest
{
    public function authorize(
        CurrentUser $currentUser,
        UserRoleService $roles
    ): bool {
        return $roles->hasAnyActiveRole(
            $currentUser->id(),
            [Role::ADMINISTRADOR]
        );
    }

    public function rules(): array
    {
        $rules = parent::rules();

        $rules['desde'] = [
            'required',
            'date_format:Y-m-d',
        ];

        $rules['hasta'] = [
            'required',
            'date_format:Y-m-d',
            'after_or_equal:desde',
        ];

        return $rules;
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'desde.required' => 'La fecha de inicio es obligatoria.',
            'hasta.required' => 'La fecha de fin es obligatoria.',
        ]);
    }
}