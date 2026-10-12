<?php

namespace App\Exceptions\Security;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** La cuenta entró con una contraseña temporal y debe cambiarla antes de usar el sistema. */
class PasswordChangeRequiredException extends Exception
{
    public const MOTIVO = 'cambio_de_clave_pendiente';

    protected $message = 'Debe cambiar su contraseña temporal antes de continuar.';

    public function render(Request $request): JsonResponse
    {
        return response()->json(['message' => $this->getMessage(), 'motivo' => self::MOTIVO], 403);
    }
}
