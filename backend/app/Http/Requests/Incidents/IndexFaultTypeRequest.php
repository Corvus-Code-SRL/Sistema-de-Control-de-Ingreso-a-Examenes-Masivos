<?php

namespace App\Http\Requests\Incidents;

use App\Models\Role;
use App\Services\Security\UserRoleService;
use App\Support\CurrentUser;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

class IndexFaultTypeRequest extends FormRequest
{
    public function authorize(CurrentUser $currentUser, UserRoleService $roles): bool
    {
        return $roles->hasAnyActiveRole(
            $currentUser->id(),
            array_merge($roles->rolesFor('incidents'), [Role::ADMINISTRADOR, Role::AUXILIAR])
        );
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('No tiene permiso para consultar los tipos de falta.');
    }

    public function rules(): array
    {
        return [];
    }
}
