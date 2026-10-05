<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    /**
     * La API no redirige a ninguna pantalla de login (la sirve la SPA y no existe una ruta
     * `login`): sin sesión responde 401. Ver Handler::unauthenticated().
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    protected function redirectTo($request)
    {
        return null;
    }
}
