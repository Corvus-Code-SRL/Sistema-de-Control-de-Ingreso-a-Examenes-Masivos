<?php

namespace App\Http\Resources\Incidents;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Fila de la lista de incidencias. No incluye descripción, evidencia, intento ni
 * revisor: el detalle vive en IncidentReviewDetailResource.
 */
class IncidentReviewResource extends JsonResource
{
    public function toArray($request): array
    {
        $r = $this->resource;

        return [
            'id'                => $r['id'],
            'tipo'              => $r['tipo'],
            'estado'            => $r['estado'],
            'persona'           => $r['persona'],
            'examen'            => $r['examen'],
            'tipo_falta'        => $r['tipo_falta'],
            'reportante'        => $r['reportante'],
            'fecha_reporte'     => $r['fecha_reporte'],
            'hora_reporte'      => $r['hora_reporte'],
            'ambiente'          => $r['ambiente'],
            'en_central_riesgo' => $r['en_central_riesgo'],
        ];
    }
}
