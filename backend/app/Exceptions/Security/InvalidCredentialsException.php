<?php

namespace App\Exceptions\Security;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Identificador o contraseña incorrectos. El mensaje es el mismo exista o no la cuenta,
 * para no revelar qué códigos SIS están registrados.
 */
class InvalidCredentialsException extends Exception
{
    protected $message = 'Código SIS o contraseña incorrectos.';

    public function render(Request $request): JsonResponse
    {
        return response()->json(['message' => $this->getMessage(), 'motivo' => 'credenciales_invalidas'], 401);
    }
}
