<?php

namespace App\Http\Requests\Incidents;

use App\Models\Role;
use App\Services\Incidents\ExamSituationAccessService;
use App\Services\Security\UserRoleService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BrowseExamSituationsRequest extends FormRequest
{
    public function authorize(UserRoleService $roles): bool
    {
        return $roles->hasAnyActiveRole(
            $this->user()?->id_usuario,
            array_merge($roles->rolesFor('teaching'), [Role::AUXILIAR])
        );
    }

    public function rules(): array
    {
        return [
            'estado' => ['sometimes', 'required', Rule::in(ExamSituationAccessService::REGISTRABLE_STATES)],
            'page' => ['sometimes', 'required', 'integer', 'min:1', 'max:1000000'],
        ];
    }
}
