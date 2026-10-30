<?php

namespace App\Http\Resources\Incidents;

use Illuminate\Http\Resources\Json\JsonResource;

class SituationExamResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id_examen' => (int) $this->id_examen,
            'nombre_examen' => $this->nombre_examen,
            'fecha' => $this->fecha->format('Y-m-d'),
            'estado' => $this->estado,
        ];
    }
}