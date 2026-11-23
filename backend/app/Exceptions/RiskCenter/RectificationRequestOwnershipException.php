<?php

namespace App\Exceptions\RiskCenter;

use App\Support\ApiResponse;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Solo el docente que aprobó el reporte puede responder la solicitud (HU-062). Responde 403. */
class RectificationRequestOwnershipException extends Exception
{
    protected $message = 'Solo el docente que aprobó este registro puede responder la solicitud de rectificación.';

    public function render(Request $request): JsonResponse
    {
        return ApiResponse::error($this->getMessage(), 403);
    }
}
