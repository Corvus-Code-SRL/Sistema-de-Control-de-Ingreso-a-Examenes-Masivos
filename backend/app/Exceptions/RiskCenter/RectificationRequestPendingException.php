<?php

namespace App\Exceptions\RiskCenter;

use App\Support\ApiResponse;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Ya hay una solicitud de rectificación pendiente para el registro (HU-062). Responde 409. */
class RectificationRequestPendingException extends Exception
{
    public static function make(): self
    {
        return new self('Este registro ya tiene una solicitud de rectificación pendiente de respuesta del docente.');
    }

    public function render(Request $request): JsonResponse
    {
        return ApiResponse::error($this->getMessage(), 409);
    }
}
