<?php

namespace App\Http\Resources\Security;

use Illuminate\Http\Resources\Json\JsonResource;

/** Envuelve el momento (Carbon) en que se confirmó la contraseña. */
class PasswordConfirmationResource extends JsonResource
{
    public function toArray($request): array
    {
        return ['password_confirmado_en' => $this->resource->toIso8601String()];
    }
}
