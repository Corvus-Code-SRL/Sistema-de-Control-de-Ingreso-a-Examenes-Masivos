<?php

namespace App\Http\Middleware;

use App\Exceptions\Security\PasswordChangeRequiredException;
use Closure;
use Illuminate\Http\Request;

/**
 * HU-001: una cuenta con contraseña temporal no usa ninguna ruta hasta cambiarla.
 *
 * Va después de account.active en todo el grupo protegido. Responde 403 `cambio_de_clave_pendiente`
 * sin revocar el token: la sesión sigue viva para poder cambiar la contraseña. Las rutas que lo
 * permiten (GET /auth/yo, POST /auth/logout y POST /auth/cambiar-contrasenia) se eximen con
 * withoutMiddleware().
 */
class EnsurePasswordIsChanged
{
    /**
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user !== null && $user->debe_cambiar_contrasenia === true) {
            throw new PasswordChangeRequiredException();
        }

        return $next($request);
    }
}
