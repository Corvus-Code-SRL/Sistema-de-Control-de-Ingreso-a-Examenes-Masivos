<?php

namespace App\Http\Resources\Exams;

use Illuminate\Http\Resources\Json\JsonResource;

class ClassroomResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id_ambiente' => $this->id_ambiente,
            'nro_aula'    => $this->nro_aula,
            'capacidad'   => $this->capacidad,
        ];
    }
}
