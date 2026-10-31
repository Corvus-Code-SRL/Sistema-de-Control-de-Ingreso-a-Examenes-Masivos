<?php

namespace App\Http\Middleware;

use App\Exceptions\Security\InactiveAccountException;
use App\Services\Security\AuthService;
use App\Support\RecordStatus;
use Closure;
use Illuminate\Http\Request;

/**
 * RNF-02: una cuenta deshabilitada pierde el acceso aunque conserve un token vigente.
 *
 * Va después de auth:sanctum en todo el grupo protegido. Responde 403 `cuenta_inactiva` y revoca el
 * token en uso, así que la siguiente petición con ese token ya es un 401.
 */
class EnsureAccountIsActive
{
    private AuthService $auth;

    public function __construct(AuthService $auth)
    {
        $this->auth = $auth;
    }

    /**
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user !== null && $user->estado !== RecordStatus::ACTIVE) {
            $this->auth->cerrarSesion($user->currentAccessToken());

            throw new InactiveAccountException();
        }

        return $next($request);
    }
}
