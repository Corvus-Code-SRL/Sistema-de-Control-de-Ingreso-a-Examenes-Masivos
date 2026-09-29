<?php

namespace App\Http\Resources\Exams;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Auxiliar habilitado para un examen con el ambiente que tiene asignado (HU-09).
 * `ambiente` es null mientras el docente no le asigne uno.
 */
class ExamAssistantResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id_examen'   => $this->id_examen,
            'id_usuario'  => $this->id_usuario,
            'id_ambiente' => $this->id_ambiente,

            'nombre_completo' => $this->whenLoaded('assistant', fn () => $this->assistant->nombre_completo),
            'cod_sis'         => $this->whenLoaded('assistant', fn () => $this->assistant->cod_sis),

            'ambiente' => $this->whenLoaded(
                'classroom',
                fn () => $this->classroom ? new ClassroomResource($this->classroom) : null
            ),
        ];
    }
}