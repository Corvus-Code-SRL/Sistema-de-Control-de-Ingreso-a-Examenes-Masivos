<?php

namespace App\Http\Resources\Reports\Academic;

use Illuminate\Http\Resources\Json\JsonResource;

class AlienAttemptsReportResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id_estudiante' => (int) $this->id_estudiante,
            'nombre_completo' => trim($this->nombre_completo),
            'cod_sis' => $this->cod_sis,
            'intentos' => (int) $this->intentos,
            'examenes' => explode(' · ', $this->examenes),
            'id_cr_estudiante' => $this->id_cr_estudiante !== null ? (int) $this->id_cr_estudiante : null,
        ];
    }
}
