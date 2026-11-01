<?php

namespace App\Exceptions\Exams;

use App\Support\ApiResponse;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * El reporte final solo se entrega al docente dueño de un examen finalizado.
 */
class FinalReportUnavailableException extends Exception
{
    protected $message = 'El reporte final solo está disponible para sus exámenes finalizados.';

    public function render(Request $request): JsonResponse
    {
        return ApiResponse::error($this->getMessage(), 403);
    }
}
