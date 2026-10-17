<?php

namespace App\Http\Resources\Incidents;

use Illuminate\Http\Resources\Json\JsonResource;

class IncidentReportResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id_reporte_est' => (int) $this->id_reporte_est,
            'id_intento' => (int) $this->id_intento,
            'estado' => $this->estado,
            'fecha_reporte' => $this->fecha_reporte->toIso8601String(),
        ];
    }
}