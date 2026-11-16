<?php

namespace App\Exceptions\RiskCenter;

use App\Support\ApiResponse;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * El registro ya fue rectificado (HU-062): otra persona lo retiró de la Central antes. Un registro rectificado
 * no se reactiva ni se rectifica de nuevo. Responde 409.
 */
class RiskRecordAlreadyRectifiedException extends Exception
{
    public static function make(): self
    {
        return new self('Este registro ya fue rectificado y ya no está en la Central de Riesgo.');
    }

    public function render(Request $request): JsonResponse
    {
        return ApiResponse::error($this->getMessage(), 409);
    }
}
