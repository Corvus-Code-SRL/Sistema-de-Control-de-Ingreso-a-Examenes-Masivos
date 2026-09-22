<?php

namespace App\Http\Resources\Exams;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Examen con su par materia-carrera, su estado y el docente que lo creó.
 */
class ExamResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id_examen'          => $this->id_examen,
            'nombre_examen'      => $this->nombre_examen,
            'fecha'              => $this->fecha ? $this->fecha->format('Y-m-d') : null,
            'hora_inicio'        => $this->formatTime($this->hora_inicio),
            'hora_fin'           => $this->formatTime($this->hora_fin),
            'duracion'           => $this->duracion,
            'normas'             => $this->normas,
            'estado'             => $this->estado,
            'id_tipo_examen'     => $this->id_tipo_examen,
            'id_carrera'         => $this->id_carrera,
            'id_materia'         => $this->id_materia,
            'id_usuario_docente' => $this->id_usuario_docente,

            'tipo_examen' => $this->whenLoaded('examType', fn () => [
                'id_tipo_examen' => $this->examType->id_tipo_examen,
                'nombre'         => $this->examType->nombre,
                'categoria'      => $this->examType->categoria,
            ]),

            'materia' => $this->whenLoaded('subject', fn () => [
                'id_materia' => $this->subject->id_materia,
                'nombre'     => $this->subject->nombre,
                'codigo'     => $this->subject->codigo,
            ]),

            'carrera' => $this->whenLoaded('career', fn () => [
                'id_carrera' => $this->career->id_carrera,
                'nombre'     => $this->career->nombre,
            ]),

            'ambientes' => ClassroomResource::collection($this->whenLoaded('classrooms')),
            'grupos' => GroupResource::collection($this->whenLoaded('groups')),
        ];
    }

    /** PostgreSQL devuelve time como HH:MM:SS; el cliente trabaja con HH:MM. */
    private function formatTime(?string $time): ?string
    {
        return $time === null ? null : substr($time, 0, 5);
    }
}
