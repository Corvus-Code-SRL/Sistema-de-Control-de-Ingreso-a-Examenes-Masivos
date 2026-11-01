<?php

namespace App\Http\Resources\Academic;

use Illuminate\Http\Resources\Json\JsonResource;

class FacultyResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id_facultad' => (int) $this->id_facultad,
            'nombre' => $this->nombre,
            'codigo' => $this->codigo,
        ];
    }
}
