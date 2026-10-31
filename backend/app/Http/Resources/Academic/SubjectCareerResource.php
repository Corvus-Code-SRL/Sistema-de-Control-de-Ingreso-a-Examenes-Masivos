<?php

namespace App\Http\Resources\Academic;

use App\Support\RecordStatus;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Par materia-carrera del catálogo institucional.
 *
 * Siempre expone la carrera: la misma materia puede aparecer en varias y sin ese
 * dato las entradas serían indistinguibles.
 */
class SubjectCareerResource extends JsonResource
{
    public function toArray($request): array
    {
        $groupCount = (int) $this->cantidad_grupos;

        return [
            'id_materia' => (int) $this->id_materia,
            'id_carrera' => (int) $this->id_carrera,
            'nombre' => $this->subject->nombre,
            'codigo' => $this->subject->codigo,
            'descripcion' => $this->subject->descripcion,
            'nivel_semestre' => $this->nivel_semestre,
            'obligatoria' => $this->obligatoria === null ? null : (bool) $this->obligatoria,
            'activa' => $this->estado === RecordStatus::ACTIVE
                && $this->subject->estado === RecordStatus::ACTIVE,
            'es_mia' => $groupCount > 0,
            'cantidad_grupos' => $groupCount,
            'carrera' => new CareerResource($this->career),
        ];
    }
}
