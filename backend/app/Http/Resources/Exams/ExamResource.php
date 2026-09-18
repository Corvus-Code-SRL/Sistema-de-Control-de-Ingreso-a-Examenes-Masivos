<?php

namespace App\Http\Resources\Exams;

use Illuminate\Http\Resources\Json\JsonResource;

class ExamResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id_examen'      => $this->id_examen,
            'nombre_examen'  => $this->nombre_examen,
            'fecha'          => $this->fecha?->format('Y-m-d'),
            'hora_inicio'    => $this->hora_inicio,
            'hora_fin'       => $this->hora_fin,
            'duracion'       => $this->duracion,
            'normas'         => $this->normas,
            'id_tipo_examen' => $this->id_tipo_examen,

            'tipo_examen' => $this->whenLoaded('tipo_examen', fn () => [
                'id_tipo_examen' => $this->tipo_examen->id_tipo_examen,
                'nombre'         => $this->tipo_examen->nombre,
                'categoria'      => $this->tipo_examen->categoria,
            ]),

            'ambientes' => $this->whenLoaded('ambiente', fn () =>
                $this->ambiente->map(fn ($a) => [
                    'id_ambiente' => $a->id_ambiente,
                    'nro_aula'    => $a->nro_aula,
                    'capacidad'   => $a->capacidad
                ])
            ),

            'grupos' => $this->whenLoaded('grupo', fn () =>
                $this->grupo->map(fn ($g) => [
                    'id_grupo'  => $g->id_grupo,
                    'num_grupo' => $g->num_grupo,
                    'gestion'   => $g->gestion,
                    'estado'    => $g->estado,
                ])
            ),
        ];
    }
}