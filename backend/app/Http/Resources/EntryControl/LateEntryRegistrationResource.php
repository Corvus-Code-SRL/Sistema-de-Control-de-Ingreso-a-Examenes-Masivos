<?php

namespace App\Http\Resources\EntryControl;

use App\Support\ClockTime;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ingreso con retraso que el docente registró directamente (HU-037). hora_ingreso va en
 * HH:MM, como hora_solicitud y la vista En curso.
 */
class LateEntryRegistrationResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id_estudiante' => $this->resource['id_estudiante'],
            'estado_ingreso' => $this->resource['estado_ingreso'],
            'hora_ingreso' => ClockTime::fromTime($this->resource['hora_ingreso']),
            'id_ambiente' => $this->resource['id_ambiente'],
        ];
    }
}
