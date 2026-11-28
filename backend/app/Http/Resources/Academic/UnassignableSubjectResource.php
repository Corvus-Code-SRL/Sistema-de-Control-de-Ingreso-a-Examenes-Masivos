<?php

namespace App\Http\Resources\Academic;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * HU-008: una oferta del período vista como materia desvinculable.
 * `estado` es el de la oferta (`ACTIVO`|`INACTIVO`); el front mapea `INACTIVO` a "Desvinculada".
 */
class UnassignableSubjectResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id_materia' => (int) $this->id_materia,
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'estado' => $this->estado,
            'grupos_count' => (int) $this->grupos_count,
        ];
    }
}
