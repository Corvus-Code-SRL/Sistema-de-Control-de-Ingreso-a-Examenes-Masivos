<?php

namespace App\Http\Requests\Exams;

use App\Services\Security\UserRoleService;
use App\Support\CurrentUser;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

class IndexClassroomRequest extends FormRequest
{
    public function authorize(CurrentUser $currentUser, UserRoleService $roles): bool
    {
        return $roles->isAdministrator($currentUser->id());
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('Solo un Administrador puede consultar el catálogo de ambientes.');
    }

    public function rules(): array
    {
        return [];
    }
}
