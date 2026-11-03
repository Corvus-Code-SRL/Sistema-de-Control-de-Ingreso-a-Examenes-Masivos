<?php

namespace App\Http\Resources\Incidents;

use Illuminate\Http\Resources\Json\JsonResource;

class ExamSituationContextResource extends JsonResource
{
    public function toArray($request): array
    {
        $exam = $this->resource['exam'];
        $page = $this->resource['page'];
        $summaries = $this->resource['summaries'];

        return [
            'examen' => (new SituationExamResource($exam))->resolve($request),
            'ambiente_control' => $this->resource['room_scope'],
            'ambientes' => $exam->classrooms->map(fn ($room) => [
                'id_ambiente' => (int) $room->id_ambiente,
                'nro_aula' => $room->nro_aula,
            ])->values()->all(),
            'hora_servidor' => $this->resource['server_time'],
            'estudiantes' => $page->getCollection()->map(function ($student) use ($summaries) {
                $id = (int) $student->id_estudiante;

                return [
                    'id_estudiante' => $id,
                    'cod_sis' => $student->cod_sis,
                    'nombre_completo' => trim(implode(' ', array_filter([
                        $student->nombre, $student->apellido_paterno, $student->apellido_materno,
                    ]))),
                    'estado_ingreso' => $student->estado_ingreso,
                    'ambiente' => [
                        'id_ambiente' => (int) $student->id_ambiente,
                        'nro_aula' => $student->nro_aula,
                    ],
                    'situaciones' => $summaries[$id] ?? [
                        'identidad_observada' => false, 'ultimo_cambio_ambiente' => null,
                    ],
                ];
            })->values()->all(),
            'paginacion' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
        ];
    }
}