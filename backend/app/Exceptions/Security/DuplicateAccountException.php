<?php

namespace App\Exceptions\Security;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * El código SIS ya tiene una cuenta en SCIEM (CA 4).
 */
class DuplicateAccountException extends Exception
{
    public function __construct(string $fullName)
    {
        parent::__construct("Ya existe una cuenta con este código SIS: {$fullName}.");
    }

    public function render(Request $request): JsonResponse
    {
        // Mismo formato que un error de validación: el frontend lo pinta bajo el campo cod_sis.
        return response()->json([
            'message' => 'Los datos proporcionados no son válidos.',
            'errors'  => ['cod_sis' => [$this->getMessage()]],
        ], 422);
    }
}
