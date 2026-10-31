<?php

namespace App\Exceptions\Security;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** La cuenta existe y la contraseña es correcta, pero está deshabilitada. */
class InactiveAccountException extends Exception
{
    protected $message = 'Su cuenta está deshabilitada. Contacte al Administrador.';

    public function render(Request $request): JsonResponse
    {
        return response()->json(['message' => $this->getMessage(), 'motivo' => 'cuenta_inactiva'], 403);
    }
}
