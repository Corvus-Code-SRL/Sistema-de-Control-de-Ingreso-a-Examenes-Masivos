<?php

namespace App\Http\Requests\EntryControl;

use App\Models\Role;
use App\Services\Security\UserRoleService;
use Illuminate\Foundation\Http\FormRequest;

class IndexEntryAttemptRequest extends FormRequest
{
    public function authorize(UserRoleService $roles): bool
    {
        return $roles->hasAnyActiveRole($this->user()->id_usuario, [Role::DOCENTE, Role::AUXILIAR]);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['examen' => $this->route('examen')]);
    }

    public function rules(): array
    {
        return [
            'examen' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'id_ambiente' => ['required', 'integer', 'min:1', 'max:2147483647'],
        ];
    }
}
