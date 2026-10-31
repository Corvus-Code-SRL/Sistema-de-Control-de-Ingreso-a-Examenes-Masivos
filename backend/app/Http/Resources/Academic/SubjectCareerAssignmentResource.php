<?php

namespace App\Http\Resources\Academic;

use Illuminate\Http\Resources\Json\JsonResource;

/** Una oferta académica: un par materia-carrera ofertado en un período. `estado` es el de la oferta. */
class SubjectCareerAssignmentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id_carrera' => (int) $this->id_carrera,
            'id_materia' => (int) $this->id_materia,
            'id_periodo' => (int) $this->id_gestion_periodo,
            'estado' => $this->estado,
            'carrera' => new CareerResource($this->career),
            'materia' => new SubjectResource($this->subject),
            'periodo' => new PeriodResource($this->period),
        ];
    }
}
