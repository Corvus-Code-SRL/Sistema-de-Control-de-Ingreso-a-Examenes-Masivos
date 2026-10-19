<?php

namespace App\Http\Requests\Incidents;

use App\Models\Role;
use App\Services\Security\UserRoleService;
use App\Support\CurrentUser;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

class ListIncidentReportsRequest extends FormRequest
{
    public function authorize(CurrentUser $currentUser, UserRoleService $roles): bool
    {
        return $roles->hasAnyActiveRole($currentUser->id(), [Role::DOCENTE]);
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('No tiene permiso para consultar incidencias.');
    }

    public function rules(): array
    {
        return [
            'estado' => ['nullable', 'in:pendiente,revisada'],
            'examen' => ['nullable', 'integer', 'min:1', 'max:2147483647'],
        ];
    }
}
