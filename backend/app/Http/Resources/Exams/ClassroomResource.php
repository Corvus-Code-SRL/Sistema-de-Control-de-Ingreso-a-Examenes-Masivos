<?php

namespace App\Http\Resources\Exams;

use App\Support\RecordStatus;
use Illuminate\Http\Resources\Json\JsonResource;

class ClassroomResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id_ambiente' => (int) $this->id_ambiente,
            'nro_aula' => $this->nro_aula,
            'capacidad' => (int) $this->capacidad,
            'ubicacion' => $this->ubicacion,
            'activo' => $this->estado === RecordStatus::ACTIVE,
        ];
    }
}