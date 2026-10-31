<?php

namespace App\Http\Resources\Exams;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Examen que controla un auxiliar y el ambiente donde controla (HU-09).
 * `ambiente` es null mientras el docente no le asigne uno y el examen tenga varios. Si el examen tiene
 * un solo ambiente, ese es el ambiente y `ambiente_por_defecto` es true (no lo asignó el docente).
 */
class AssistantExamResource extends JsonResource
{
    public function toArray($request): array
    {
        $exam = $this->exam;

        return [
            'id_examen'     => $exam->id_examen,
            'nombre_examen' => $exam->nombre_examen,
            'fecha'         => $exam->fecha ? $exam->fecha->format('Y-m-d') : null,
            'hora_inicio'   => $this->formatTime($exam->hora_inicio),
            'hora_fin'      => $this->formatTime($exam->hora_fin),
            'estado'        => $exam->estado,
            'materia'       => $exam->subject ? $exam->subject->nombre : null,
            'ambiente'      => $this->classroom ? new ClassroomResource($this->classroom) : null,
            'ambiente_por_defecto' => (bool) $this->getAttribute('ambiente_por_defecto'),
        ];
    }

    /** PostgreSQL devuelve time como HH:MM:SS; el cliente trabaja con HH:MM. */
    private function formatTime(?string $time): ?string
    {
        return $time === null ? null : substr($time, 0, 5);
    }
}
