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
    private ?string $userId;

    public function __construct(string $fullName, ?string $userId = null)
    {
        parent::__construct("Ya existe una cuenta con este código SIS: {$fullName}.");

        $this->userId = $userId;
    }

    public function render(Request $request): JsonResponse
    {
        // Mismo formato que un error de validación: el frontend lo pinta bajo el campo cod_sis.
        return response()->json([
            'message' => 'Los datos proporcionados no son válidos.',
            'errors'  => ['cod_sis' => [$this->getMessage()]],
            // Permite ofrecer «Ver la cuenta existente».
            'cuenta_existente' => ['id_usuario' => $this->userId],
        ], 422);
    }
}
