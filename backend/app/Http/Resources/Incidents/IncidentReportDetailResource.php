<?php

namespace App\Http\Resources\Incidents;

use Illuminate\Http\Resources\Json\JsonResource;

/** Detalle completo de una incidencia: la lista más lo que se necesita para decidir. */
class IncidentReportDetailResource extends JsonResource
{
    public function toArray($request): array
    {
        $r = $this->resource;

        return array_merge(
            (new IncidentReportResource($r))->toArray($request),
            [
                'descripcion'   => $r['descripcion'],
                'evidencia_url' => $r['evidencia_url'],
                'evidencia'     => $r['evidencia'],
                'intento'       => $r['intento'],
                'revisor'       => $r['revisor'],
            ]
        );
    }
}