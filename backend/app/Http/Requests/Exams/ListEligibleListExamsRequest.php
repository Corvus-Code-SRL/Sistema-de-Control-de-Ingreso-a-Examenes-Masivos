<?php

namespace App\Http\Requests\Exams;

use App\Services\Security\UserRoleService;
use App\Support\CurrentUser;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

class ListEligibleListExamsRequest extends FormRequest
{
    public function authorize(CurrentUser $currentUser, UserRoleService $roles): bool
    {
        return $roles->canUse($currentUser->id(), 'eligible_list');
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('No tiene permiso para ver esta sección.');
    }

    public function rules(): array
    {
        return [];
    }
}
