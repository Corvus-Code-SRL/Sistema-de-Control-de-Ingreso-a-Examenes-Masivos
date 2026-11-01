<?php

namespace App\Http\Requests\Security;

use Illuminate\Auth\Access\AuthorizationException;

/**
 * Consulta de un código INV libre para un docente invitado (HU-001): solo un Administrador activo.
 */
class GuestCodeRequest extends AdministratorRequest
{
    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('Solo un Administrador puede obtener códigos de docente invitado.');
    }
}
