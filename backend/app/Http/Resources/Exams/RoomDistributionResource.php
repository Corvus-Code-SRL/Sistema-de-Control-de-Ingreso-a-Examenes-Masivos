<?php

namespace App\Http\Resources\Exams;

use App\Services\Exams\Distribution\RoomDistributionQueryService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @phpstan-import-type DistributionView from RoomDistributionQueryService */
class RoomDistributionResource extends JsonResource
{
    /**
     * @param Request $request
     * @return DistributionView
     */
    public function toArray($request): array
    {
        return [
            'examen' => (new DistributionExamOptionResource($this->resource['examen']))->resolve($request),
            'editable' => $this->resource['editable'],
            'motivo_solo_lectura' => $this->resource['motivo_solo_lectura'],
            'version' => $this->resource['version'],
            'total_habilitados' => $this->resource['total_habilitados'],
            'total_no_habilitados' => $this->resource['total_no_habilitados'],
            'capacidad_total' => $this->resource['capacidad_total'],
            'faltan_asientos' => $this->resource['faltan_asientos'],
            'ambientes' => $this->resource['ambientes'],
            'estudiantes' => $this->resource['estudiantes'],
        ];
    }
}
