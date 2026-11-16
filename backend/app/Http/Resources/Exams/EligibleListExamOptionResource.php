<?php

namespace App\Http\Resources\Exams;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Item del selector de exámenes de HU-028.
 *
 * Shape mínimo para el dropdown: solo lo que el docente necesita para
 * elegir el examen. No expone grupos, ambientes ni auxiliares (eso es de
 * la vista de exámenes, no de HU-028).
 */
class EligibleListExamOptionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id_examen' => (int) $this->id_examen,
            'nombre' => (string) $this->nombre_examen,
            'materia' => $this->subject === null
                ? null
                : [
                    'id' => (int) $this->subject->id_materia,
                    'nombre' => (string) $this->subject->nombre,
                ],
            'carrera' => $this->career === null
                ? null
                : [
                    'id' => (int) $this->career->id_carrera,
                    'nombre' => (string) $this->career->nombre,
                ],
            'grupos' => $this->groups->pluck('num_grupo')->values(),
            'fecha' => $this->fecha->format('Y-m-d'),
            'hora_inicio' => (string) $this->hora_inicio,
            'estado' => (string) $this->estado,
        ];
    }
}
