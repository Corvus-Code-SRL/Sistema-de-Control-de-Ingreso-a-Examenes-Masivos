<?php

namespace App\Http\Resources\Academic;

use Illuminate\Http\Resources\Json\JsonResource;

class CareerResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id_carrera' => (int) $this->id_carrera,
            'nombre' => $this->nombre,
            'codigo' => $this->codigo,
            'id_facultad' => (int) $this->id_facultad,
        ];
    }
}
