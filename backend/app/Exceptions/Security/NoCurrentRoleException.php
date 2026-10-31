<?php

namespace App\Exceptions\Security;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** La cuenta está activa pero no tiene ninguna asignación de rol abierta. */
class NoCurrentRoleException extends Exception
{
    protected $message = 'Su cuenta no tiene un rol vigente. Contacte al Administrador.';

    public function render(Request $request): JsonResponse
    {
        return response()->json(['message' => $this->getMessage(), 'motivo' => 'sin_rol_vigente'], 403);
    }
}
