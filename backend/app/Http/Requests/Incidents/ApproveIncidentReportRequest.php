<?php

namespace App\Http\Requests\Incidents;

use App\Models\Role;
use App\Services\Security\UserRoleService;
use App\Support\CurrentUser;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

class ApproveIncidentReportRequest extends FormRequest
{
    public function authorize(CurrentUser $currentUser, UserRoleService $roles): bool
    {
        return $roles->canUse($currentUser->id(), 'incidents');
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('No tiene permiso para revisar incidencias.');
    }

    public function rules(): array
    {
        return [
            'observacion' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
