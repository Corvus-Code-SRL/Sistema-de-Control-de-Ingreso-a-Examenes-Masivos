<?php

namespace App\Http\Resources\Academic;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Examen del docente como opción para registrar un estudiante excepcional (HU-023).
 * Solo se ofrecen los exámenes En curso del docente responsable.
 */
class ExceptionalExamResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id_examen' => (int) $this->id_examen,
            'nombre_examen' => (string) $this->nombre_examen,
            'fecha' => (string) $this->fecha,
            'hora_inicio' => (string) $this->hora_inicio,
            'estado' => (string) $this->estado,
        ];
    }
}
