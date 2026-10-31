<?php

namespace App\Http\Requests\Security;

use App\Services\Security\UserRoleService;
use App\Support\CurrentUser;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Verificación del código SIS antes de registrar una cuenta (HU-001): solo un Administrador activo.
 */
class VerifySisRequest extends FormRequest
{
    public function authorize(CurrentUser $currentUser, UserRoleService $roles): bool
    {
        return $roles->isAdministrator($currentUser->id());
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('Solo un Administrador puede verificar códigos SIS.');
    }

    public function rules(): array
    {
        return [];
    }
}
