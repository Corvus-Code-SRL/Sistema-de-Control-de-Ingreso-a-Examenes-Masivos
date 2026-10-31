<?php

namespace App\Http\Resources\Exams;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Datos del reporte final tal como los arma FinalReportService (HU-051).
 */
class FinalReportResource extends JsonResource
{
    public function toArray($request): array
    {
        return $this->resource;
    }
}
