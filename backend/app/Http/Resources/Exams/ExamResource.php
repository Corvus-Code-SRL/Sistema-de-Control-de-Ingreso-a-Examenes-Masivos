<?php

namespace App\Http\Resources\Exams;

use Illuminate\Http\Resources\Json\JsonResource;

class ExamResource extends JsonResource
{
    public function toArray($request): array
    {
        $firstGroup = $this->relationLoaded('groups') ? $this->groups->first() : null;
        $materia = ($firstGroup && $firstGroup->relationLoaded('subject')) ? $firstGroup->subject : null;

        return [
            'id_examen'      => $this->id_examen,
            'nombre_examen'  => $this->nombre_examen,
            'fecha'          => $this->fecha?->format('Y-m-d'),
            'hora_inicio'    => $this->hora_inicio,
            'hora_fin'       => $this->hora_fin,
            'duracion'       => $this->duracion,
            'normas'         => $this->normas,
            'id_tipo_examen' => $this->id_tipo_examen,

            'materia' => $materia ? [
                'id_materia' => $materia->id_materia,
                'nombre'     => $materia->nombre,
                'codigo'     => $materia->codigo,
            ] : null,

            'tipo_examen' => $this->whenLoaded('examType', fn () => [
                'id_tipo_examen' => $this->examType->id_tipo_examen,
                'nombre'         => $this->examType->nombre,
                'categoria'      => $this->examType->categoria,
            ]),

            'ambientes' => $this->whenLoaded('classrooms', fn () =>
                $this->classrooms->map(fn ($a) => [
                    'id_ambiente' => $a->id_ambiente,
                    'nro_aula'    => $a->nro_aula,
                    'capacidad'   => $a->capacidad,
                    'ubicacion'   => $a->ubicacion ?? null,
                ])
            ),

            'grupos' => $this->whenLoaded('groups', fn () =>
                $this->groups->map(fn ($g) => [
                    'id_grupo'  => $g->id_grupo,
                    'num_grupo' => $g->num_grupo,
                    'gestion'   => $g->gestion,
                    'estado'    => $g->estado,
                    'cantidad_estudiantes' => $g->cantidad_estudiantes ?? 0,
                ])
            ),
        ];
    }
}