<?php

namespace App\Http\Resources\Security;

use Illuminate\Http\Resources\Json\JsonResource;

/** Confirma el cambio de contraseña; nunca devuelve valores de contraseña. */
class PasswordChangedResource extends JsonResource
{
    public function toArray($request): array
    {
        return ['debe_cambiar_contrasenia' => false];
    }
}
