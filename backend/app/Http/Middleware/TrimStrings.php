<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\TrimStrings as Middleware;

class TrimStrings extends Middleware
{
    /**
     * The names of the attributes that should not be trimmed.
     *
     * @var array<int, string>
     */
    protected $except = [
        'current_password',
        'password',
        'password_confirmation',
        'contrasenia_temporal',
        'contrasenia_actual',
        'contrasenia_nueva',
        'contrasenia_nueva_confirmation',
    ];
}
