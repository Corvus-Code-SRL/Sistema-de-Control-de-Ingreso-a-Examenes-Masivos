<?php

namespace App\Http\Resources\Incidents;

use Illuminate\Http\Resources\Json\JsonResource;

class FaultTypeResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id_falta' => (int) $this->id_falta,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
        ];
    }
}
