<?php

namespace App\Http\Requests\Incidents;

use App\Models\Role;
use App\Services\Security\UserRoleService;
use Illuminate\Foundation\Http\FormRequest;

/** Exámenes en curso donde el usuario puede reportar incidencias (HU-042). */
class BrowseInProgressIncidentExamsRequest extends FormRequest
{
    public function authorize(UserRoleService $roles): bool
    {
        return $roles->hasAnyActiveRole(
            $this->user()?->id_usuario,
            array_merge($roles->rolesFor('incidents'), [Role::AUXILIAR])
        );
    }

    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'required', 'integer', 'min:1', 'max:1000000'],
        ];
    }
}
