<?php

namespace App\Exceptions\Security;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * El código INV que se mostró al administrador lo tomó otra cuenta antes de registrarla.
 *
 * La respuesta (409) trae un código libre para que lo vea antes de volver a intentar.
 */
class GuestCodeTakenException extends Exception
{
    private string $suggestedCode;

    public function __construct(string $takenCode, string $suggestedCode)
    {
        parent::__construct(
            "El código {$takenCode} ya fue asignado a otra cuenta. Se propone el código {$suggestedCode}."
        );

        $this->suggestedCode = $suggestedCode;
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'codigo_sugerido' => $this->suggestedCode,
        ], 409);
    }
}
