<?php

namespace App\Http\Requests\Academic;

use App\Models\Role;
use App\Services\Security\UserRoleService;
use App\Support\CurrentUser;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

class ListFacultiesRequest extends FormRequest
{
    public function authorize(CurrentUser $currentUser, UserRoleService $roles): bool
    {
        return $roles->hasAnyActiveRole($currentUser->id(), [Role::ADMINISTRADOR]);
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('No tiene permiso para consultar las facultades.');
    }

    public function rules(): array
    {
        return [];
    }
}
