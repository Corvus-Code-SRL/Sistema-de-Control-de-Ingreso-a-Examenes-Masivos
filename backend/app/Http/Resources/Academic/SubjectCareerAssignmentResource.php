<?php

namespace App\Http\Resources\Academic;

use Illuminate\Http\Resources\Json\JsonResource;

class SubjectCareerAssignmentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id_carrera' => (int) $this->id_carrera,
            'id_materia' => (int) $this->id_materia,
            'estado' => $this->estado,
            'carrera' => new CareerResource($this->career),
            'materia' => new SubjectResource($this->subject),
        ];
    }
}