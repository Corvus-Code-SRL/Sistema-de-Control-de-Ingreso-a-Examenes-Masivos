<?php

namespace App\Http\Resources\EntryControl;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Verificación del ingreso con retraso: el mismo resultado de HU-032 (StudentVerificationResource)
 * más la solicitud pendiente del estudiante, si la hay.
 */
class LateEntryVerificationResource extends JsonResource
{
    public function toArray($request): array
    {
        $pending = $this->resource['solicitud_pendiente'];
        $pendingRequest = $pending === null ? null : (new LateEntryRequestResource($pending))->toArray($request);

        return (new StudentVerificationResource($this->resource['verificacion']))->toArray($request) + [
            'solicitud_pendiente' => $pendingRequest,
        ];
    }
}
