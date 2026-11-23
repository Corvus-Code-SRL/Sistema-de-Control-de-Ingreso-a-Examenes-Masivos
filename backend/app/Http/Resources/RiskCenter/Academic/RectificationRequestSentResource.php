<?php

namespace App\Http\Resources\RiskCenter\Academic;

use Illuminate\Http\Resources\Json\JsonResource;

/** La solicitud recién enviada por el Administrador (HU-062): a quién, cuándo y hasta cuándo puede responder. */
class RectificationRequestSentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id_solicitud_rect' => (int) $this->id_solicitud_rect,
            'id_cr_estudiante' => (int) $this->id_cr_estudiante,
            'estado' => $this->effectiveState(),
            'motivo' => $this->motivo_solicitud,
            'docente' => ['nombre_completo' => $this->teacher?->nombre_completo],
            'fecha_solicitud' => $this->fecha_solicitud->toIso8601String(),
            'vence_en' => $this->vence_en->toIso8601String(),
        ];
    }
}
