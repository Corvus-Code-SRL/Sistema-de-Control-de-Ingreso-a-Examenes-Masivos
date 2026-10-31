<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken;

/**
 * Token de sesión de Sanctum (public.personal_access_tokens), con el momento de la última
 * confirmación de contraseña.
 *
 * @property \Carbon\Carbon|null $password_confirmado_en
 */
class AccessToken extends PersonalAccessToken
{
    protected $table = 'personal_access_tokens';

    protected $casts = [
        'abilities' => 'json',
        'last_used_at' => 'datetime',
        'password_confirmado_en' => 'datetime',
    ];
}
