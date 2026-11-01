<?php

namespace App\Http\Requests\Incidents;

use App\Models\Role;
use App\Services\Security\UserRoleService;
use Illuminate\Foundation\Http\FormRequest;

class ListExamSituationsRequest extends FormRequest
{
    public function authorize(UserRoleService $roles): bool
    {
        return $roles->hasAnyActiveRole(
            $this->user()?->id_usuario,
            array_merge($roles->rolesFor('teaching'), [Role::AUXILIAR])
        );
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['examen' => $this->route('examen')]);
    }

    public function rules(): array
    {
        return [
            'examen' => ['required', 'integer', 'min:1', 'max:999999999'],
            'id_ambiente' => ['sometimes', 'required', 'integer', 'min:1', 'max:999999999'],
            'page' => ['sometimes', 'required', 'integer', 'min:1', 'max:1000000'],
        ];
    }
}