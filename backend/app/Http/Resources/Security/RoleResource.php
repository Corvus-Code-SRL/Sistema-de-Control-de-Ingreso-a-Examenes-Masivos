<?php

namespace App\Http\Resources\Security;

use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id_rol'      => $this->id_rol,
            'nombre_rol'  => $this->nombre_rol,
            'descripcion' => $this->descripcion,
            'estado'      => $this->estado,
        ];
    }
}
