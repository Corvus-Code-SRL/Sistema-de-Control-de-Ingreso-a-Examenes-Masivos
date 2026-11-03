<?php

namespace App\Http\Resources\Exams;

use Illuminate\Http\Resources\Json\JsonResource;

/** Datos de consulta del historial, sin acciones de escritura. */
class ExamHistoryResource extends JsonResource
{
    public function toArray($request): array
    {
        return $this->resource;
    }
}
