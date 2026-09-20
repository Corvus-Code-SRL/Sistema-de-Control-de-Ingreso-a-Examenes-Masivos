<?php

namespace App\Http\Resources\Academic;

use Illuminate\Http\Resources\Json\JsonResource;

class SubjectResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id_materia'  => $this->id_materia,
            'nombre'      => $this->nombre,
            'codigo'      => $this->codigo,
            'descripcion' => $this->descripcion,
            'estado'      => $this->estado,
        ];
    }
}