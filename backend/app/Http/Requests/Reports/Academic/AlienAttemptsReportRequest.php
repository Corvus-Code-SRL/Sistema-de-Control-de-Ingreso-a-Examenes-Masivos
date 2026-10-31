<?php

namespace App\Http\Requests\Reports\Academic;

use App\Models\Role;
use App\Services\Security\UserRoleService;
use App\Support\CurrentUser;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

class AlienAttemptsReportRequest extends FormRequest
{
    public function authorize(CurrentUser $currentUser, UserRoleService $roles): bool
    {
        return $roles->hasAnyActiveRole($currentUser->id(), [Role::ADMINISTRADOR]);
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('No tiene permiso para consultar este reporte.');
    }

    public function rules(): array
    {
        return [
            'desde'    => ['required', 'date'],
            'hasta'    => ['required', 'date', 'after_or_equal:desde'],
            'facultad' => ['nullable', 'integer', 'min:1', 'max:2147483647'],
            'page'     => ['nullable', 'integer', 'min:1', 'max:100000'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'hasta.after_or_equal' => 'La fecha de inicio debe ser anterior a la fecha de fin.',
        ];
    }
}
