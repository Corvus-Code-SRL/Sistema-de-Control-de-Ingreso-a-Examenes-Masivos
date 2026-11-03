<?php

namespace App\Http\Resources\Incidents;

use Illuminate\Http\Resources\Json\JsonResource;

/** Contexto de HU-057 más la cantidad de incidencias en curso de cada estudiante. */
class InProgressIncidentContextResource extends JsonResource
{
    public function toArray($request): array
    {
        $data = (new ExamSituationContextResource($this->resource))->resolve($request);
        $counts = $this->resource['incident_counts'];

        $data['estudiantes'] = array_map(function (array $student) use ($counts) {
            $student['total_incidencias'] = (int) ($counts[$student['id_estudiante']] ?? 0);

            return $student;
        }, $data['estudiantes']);

        return $data;
    }
}
