<?php

namespace App\Http\Requests\Incidents;

use App\Models\Role;
use App\Services\Security\UserRoleService;
use App\Support\CurrentUser;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

class RejectIncidentReportRequest extends FormRequest
{
    public function authorize(CurrentUser $currentUser, UserRoleService $roles): bool
    {
        return $roles->hasAnyActiveRole($currentUser->id(), [Role::DOCENTE]);
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('No tiene permiso para revisar incidencias.');
    }

    public function rules(): array
    {
        return [
            'observacion' => ['required', 'string', 'max:2000'],
        ];
    }

    /** El texto exacto del CA-4 y del mockup: la vista lo pinta inline bajo el campo. */
    public function messages(): array
    {
        return [
            'observacion.required' => 'La observación es obligatoria.',
        ];
    }
}