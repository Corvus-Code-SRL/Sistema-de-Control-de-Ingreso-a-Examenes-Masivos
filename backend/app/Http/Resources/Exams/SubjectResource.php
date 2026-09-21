<?php

namespace App\Http\Resources\Exams;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Par materia-carrera seleccionable al crear un examen.
 *
 * Expone la carrera: la misma materia puede estar en varias y sin ella las opciones
 * del formulario serían indistinguibles.
 */
class SubjectResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id_carrera' => (int) $this->id_carrera,
            'id_materia' => (int) $this->id_materia,
            'nombre'     => $this->subject->nombre,
            'codigo'     => $this->subject->codigo,
            'carrera'    => $this->career->nombre,
            'es_mia'     => (int) $this->cantidad_grupos > 0,
        ];
    }
}
