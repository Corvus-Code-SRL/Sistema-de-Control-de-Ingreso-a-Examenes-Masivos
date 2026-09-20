<?php

namespace App\Exceptions\Security;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SisNoValidoException extends Exception
{
    protected $message = 'El código SIS no corresponde a una persona reconocida por la institución.';

    public function render(Request $request): JsonResponse
    {
        // Se devuelve con formato de error de validación para que el frontend
        // lo pinte debajo del campo cod_sis, igual que los demás errores.
        return response()->json([
            'message' => 'Los datos proporcionados no son válidos.',
            'errors'  => ['cod_sis' => [$this->getMessage()]],
        ], 422);
    }
}
