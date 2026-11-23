<?php

namespace App\Exceptions\RiskCenter;

use App\Support\ApiResponse;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * La solicitud de rectificación ya no se puede responder (HU-062): venció o ya fue respondida. Responde 409.
 */
class RectificationRequestClosedException extends Exception
{
    public static function expired(): self
    {
        return new self(
            'La solicitud de rectificación venció y ya no puede responderse. El registro sigue en la Central.'
        );
    }

    public static function answered(): self
    {
        return new self('Esta solicitud de rectificación ya fue respondida.');
    }

    public function render(Request $request): JsonResponse
    {
        return ApiResponse::error($this->getMessage(), 409);
    }
}
