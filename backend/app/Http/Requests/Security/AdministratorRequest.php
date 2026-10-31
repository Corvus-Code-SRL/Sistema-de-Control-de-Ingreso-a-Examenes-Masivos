<?php

namespace App\Http\Requests\Security;

use App\Services\Security\UserRoleService;
use App\Support\CurrentUser;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Base de las peticiones que solo puede realizar un Administrador.
 *
 * La gestión de roles es exclusiva de ese rol: un Docente o una cuenta sin rol
 * reciben 403 aunque conozcan el endpoint.
 */
abstract class AdministratorRequest extends FormRequest
{
    public function authorize(CurrentUser $currentUser, UserRoleService $roles): bool
    {
        return $roles->isAdministrator($currentUser->id());
    }

    public function rules(): array
    {
        return [];
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('Solo un Administrador puede gestionar los roles de las cuentas.');
    }
}
