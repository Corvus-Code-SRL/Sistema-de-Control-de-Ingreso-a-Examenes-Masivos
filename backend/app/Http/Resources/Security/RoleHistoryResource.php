<?php

namespace App\Http\Resources\Security;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un tramo del historial de roles de una cuenta.
 *
 * fecha_fin nula indica el rol vigente.
 */
class RoleHistoryResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id_rol'       => $this->id_rol,
            'nombre_rol'   => $this->nombre_rol,
            'fecha_inicio' => $this->pivot->fecha_inicio,
            'fecha_fin'    => $this->pivot->fecha_fin,
        ];
    }
}
