<?php

namespace App\Exceptions\Incidents;

use App\Support\ApiResponse;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * El reporte no existe. Distinto de «existe pero no es suyo», que responde 403
 * (ver IncidentOwnershipException).
 */
class IncidentReportNotFoundException extends Exception
{
    protected $message = 'La incidencia no existe.';

    public function render(Request $request): JsonResponse
    {
        return ApiResponse::error($this->getMessage(), 404);
    }
}