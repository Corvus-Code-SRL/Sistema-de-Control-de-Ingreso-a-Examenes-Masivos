<?php

namespace App\Http\Requests\Security;

use App\Services\Security\UserRoleService;
use App\Support\CurrentUser;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Consulta de la bitácora: solo los roles de la función audit_log (config sciem.access), es decir el Administrador.
 */
abstract class AuditLogRequest extends FormRequest
{
    public function authorize(CurrentUser $currentUser, UserRoleService $roles): bool
    {
        return $roles->canUse($currentUser->id(), 'audit_log');
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
