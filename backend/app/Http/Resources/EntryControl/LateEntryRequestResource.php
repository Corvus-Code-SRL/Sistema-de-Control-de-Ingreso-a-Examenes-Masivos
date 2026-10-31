<?php

namespace App\Http\Resources\EntryControl;

use App\Models\LateEntryRequest;
use App\Support\ClockTime;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Solicitud de ingreso con retraso (HU-037) con el estudiante, el ambiente, quién la pidió
 * y quién la resolvió. Espera las relaciones ya cargadas. hora_solicitud es la hora local
 * que quedará como hora de ingreso si se autoriza.
 *
 * @mixin LateEntryRequest
 */
class LateEntryRequestResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id_solicitud' => (int) $this->id_solicitud,
            'estado' => $this->estado,
            'estudiante' => [
                'id_estudiante' => (int) $this->student->id_estudiante,
                'cod_sis' => $this->student->cod_sis,
                'nombre_completo' => $this->student->nombre_completo,
            ],
            'ambiente' => [
                'id_ambiente' => (int) $this->classroom->id_ambiente,
                'nro_aula' => $this->classroom->nro_aula,
            ],
            'solicitante' => ['nombre_completo' => $this->requester->nombre_completo],
            'resolutor' => $this->resolver === null ? null : ['nombre_completo' => $this->resolver->nombre_completo],
            'solicitado_en' => $this->solicitado_en->toIso8601String(),
            'hora_solicitud' => ClockTime::local($this->solicitado_en),
            'resuelto_en' => $this->resuelto_en?->toIso8601String(),
        ];
    }
}
