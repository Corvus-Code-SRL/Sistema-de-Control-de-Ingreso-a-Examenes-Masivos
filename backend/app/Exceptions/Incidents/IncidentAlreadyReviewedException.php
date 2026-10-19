<?php

namespace App\Exceptions\Incidents;

use App\Support\ApiResponse;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Un reporte revisado es final: aprobado o rechazado no admite una segunda decisión.
 * Responde 409 (conflicto con el estado actual del recurso).
 */
class IncidentAlreadyReviewedException extends Exception
{
    protected $message = 'La incidencia ya fue revisada y no puede volver a decidirse.';

    public function render(Request $request): JsonResponse
    {
        return ApiResponse::error($this->getMessage(), 409);
    }
}
