<?php

namespace App\Http\Resources\Exams;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Examen PROGRAMADO al que el docente puede invitar colaboradores (HU-026): lo que identifica a cada
 * opción del desplegable (nombre, materia, fecha, hora y ambientes).
 */
class InvitableExamResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id_examen' => $this->id_examen,
            'nombre_examen' => $this->nombre_examen,
            'fecha' => $this->fecha ? $this->fecha->format('Y-m-d') : null,
            'hora_inicio' => $this->hora_inicio === null ? null : substr($this->hora_inicio, 0, 5),
            'hora_fin' => $this->hora_fin === null ? null : substr($this->hora_fin, 0, 5),
            'materia' => $this->whenLoaded('subject', fn () => [
                'id_materia' => $this->subject->id_materia,
                'nombre' => $this->subject->nombre,
                'codigo' => $this->subject->codigo,
            ]),
            'carrera' => $this->whenLoaded('career', fn () => $this->career === null ? null : [
                'id_carrera' => $this->career->id_carrera,
                'nombre' => $this->career->nombre,
            ]),
            'ambientes' => $this->whenLoaded('classrooms', fn () => $this->classrooms
                ->map(fn ($classroom) => [
                    'id_ambiente' => (int) $classroom->id_ambiente,
                    'nro_aula' => $classroom->nro_aula,
                ])
                ->values()
                ->all()),
        ];
    }
}
