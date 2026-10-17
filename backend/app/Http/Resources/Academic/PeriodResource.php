<?php

namespace App\Http\Resources\Academic;

use Illuminate\Http\Resources\Json\JsonResource;

/** Un período académico (gestion_periodo) con su etiqueta estándar «{codigo}/{anio}». */
class PeriodResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id_periodo' => (int) $this->id_gestion_periodo,
            'etiqueta' => $this->label,
            'nombre_periodo' => $this->type_name,
            'gestion' => $this->year,
            'vigente' => (bool) $this->vigente,
        ];
    }
}
