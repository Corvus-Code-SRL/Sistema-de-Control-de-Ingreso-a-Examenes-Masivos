<?php

namespace App\Http\Resources\RiskCenter\Academic;

use Illuminate\Http\Resources\Json\JsonResource;

/** Un tipo de falta del selector de la rectificación (HU-062). */
class RectificationFaultTypeResource extends JsonResource
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
