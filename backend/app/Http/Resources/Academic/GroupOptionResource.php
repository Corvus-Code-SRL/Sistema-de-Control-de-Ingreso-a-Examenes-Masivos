<?php

namespace App\Http\Resources\Academic;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Grupo expuesto como opción para un selector.
 *
 * El `label` ya viene armado desde el service: "Materia · G1". No se
 * recalcula aquí para que el frontend no dependa del formato.
 */
class GroupOptionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id_grupo' => (int) $this->id_grupo,
            'label' => (string) $this->label,
            'num_grupo' => (string) $this->num_grupo,
        ];
    }
}