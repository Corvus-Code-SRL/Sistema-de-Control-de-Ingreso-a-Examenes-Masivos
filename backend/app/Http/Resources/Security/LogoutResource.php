<?php

namespace App\Http\Resources\Security;

use Illuminate\Http\Resources\Json\JsonResource;

class LogoutResource extends JsonResource
{
    public function toArray($request): array
    {
        return ['sesion_cerrada' => true];
    }
}
