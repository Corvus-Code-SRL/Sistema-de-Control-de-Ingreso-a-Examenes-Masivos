<?php

namespace App\Exceptions\Incidents;

use App\Support\ApiResponse;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Solo el docente dueño del examen del reporte puede verlo y revisarlo.
 * Mismo precedente que ExamOwnershipException.
 */
class IncidentOwnershipException extends Exception
{
    protected $message = 'Solo el docente del examen puede revisar esta incidencia.';

    public function render(Request $request): JsonResponse
    {
        return ApiResponse::error($this->getMessage(), 403);
    }
}