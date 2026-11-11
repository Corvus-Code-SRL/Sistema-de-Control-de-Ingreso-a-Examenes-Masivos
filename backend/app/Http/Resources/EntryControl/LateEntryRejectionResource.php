<?php

namespace App\Http\Resources\EntryControl;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Rechazo manual registrado en el ingreso con retraso (HU-037), con el mismo formato que el
 * rechazo del control de ingreso (HU-032) para que el cliente lo trate igual.
 */
class LateEntryRejectionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id_intento' => (int) $this->resource['id_intento'],
            'registrado_en' => $this->resource['registrado_en'],
            'registrado' => (bool) $this->resource['registrado'],
            'motivo' => $this->resource['motivo'],
        ];
    }
}
