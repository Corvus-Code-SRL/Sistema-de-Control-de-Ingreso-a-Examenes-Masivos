<?php

namespace App\Exceptions\RiskCenter;

use App\Support\ApiResponse;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * La rectificación no corresponde al estado actual del registro (HU-062): otra persona ya lo rectificó o
 * los valores no cambian. Responde 409.
 */
class RiskRectificationConflictException extends Exception
{
    public static function staleVersion(): self
    {
        return new self('El registro fue rectificado mientras usted lo editaba. Recargue para ver la versión vigente.');
    }

    public static function unchanged(): self
    {
        return new self('Los valores indicados son iguales a los de la versión vigente.');
    }

    public function render(Request $request): JsonResponse
    {
        return ApiResponse::error($this->getMessage(), 409);
    }
}
