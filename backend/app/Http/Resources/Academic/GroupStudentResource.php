<?php

namespace App\Http\Resources\Academic;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un estudiante inscrito en el grupo (HU-020).
 *
 * Solo lo que la lista muestra: CI, contacto y foto no salen de aquí.
 */
class GroupStudentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id_estudiante' => (int) $this->id_estudiante,
            'cod_sis' => $this->cod_sis,
            'apellido_paterno' => $this->apellido_paterno,
            'apellido_materno' => $this->apellido_materno,
            'nombre' => $this->nombre,
        ];
    }
}
