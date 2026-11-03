<?php

namespace App\Http\Requests\Incidents;

use App\Models\Role;
use App\Services\Security\UserRoleService;
use Illuminate\Foundation\Http\FormRequest;

/** Estudiantes presentes de un examen en curso, con búsqueda por SIS, nombre o CI (HU-042). */
class InProgressIncidentContextRequest extends FormRequest
{
    public function authorize(UserRoleService $roles): bool
    {
        return $roles->hasAnyActiveRole(
            $this->user()?->id_usuario,
            array_merge($roles->rolesFor('incidents'), [Role::AUXILIAR])
        );
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'examen' => $this->route('examen'),
            'q' => is_string($this->input('q')) ? trim($this->input('q')) : $this->input('q'),
        ]);
    }

    public function rules(): array
    {
        return [
            'examen' => ['required', 'integer', 'min:1', 'max:999999999'],
            'id_ambiente' => ['sometimes', 'required', 'integer', 'min:1', 'max:999999999'],
            'page' => ['sometimes', 'required', 'integer', 'min:1', 'max:1000000'],
            'q' => ['sometimes', 'nullable', 'string', 'max:50'],
        ];
    }
}
