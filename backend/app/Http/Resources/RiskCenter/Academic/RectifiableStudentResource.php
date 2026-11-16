<?php

namespace App\Http\Resources\RiskCenter\Academic;

use Illuminate\Http\Resources\Json\JsonResource;

/** Una coincidencia de la búsqueda de estudiantes para rectificar (HU-062). */
class RectifiableStudentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id_estudiante' => (int) $this->id_estudiante,
            'nombre_completo' => $this->nombre_completo,
            'cod_sis' => $this->cod_sis,
            'ci' => $this->ci,
        ];
    }
}
