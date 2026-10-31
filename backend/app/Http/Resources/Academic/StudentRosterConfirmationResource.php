<?php

namespace App\Http\Resources\Academic;

use Illuminate\Http\Resources\Json\JsonResource;

class StudentRosterConfirmationResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'total_filas' => $this->resource->totalRows(),
            'filas_inconsistentes' => $this->resource->inconsistentRows(),
            'estudiantes_creados' => $this->resource->createdStudents(),
            'estudiantes_inscritos' => $this->resource->enrolledStudents(),
            'ya_inscritos' => $this->resource->alreadyEnrolled(),
        ];
    }
}