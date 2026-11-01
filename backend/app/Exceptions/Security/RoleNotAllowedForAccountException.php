<?php

namespace App\Exceptions\Security;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * El rol no corresponde al tipo de cuenta: una cuenta de docente invitado (código INV) solo puede
 * tener el rol Invitado (la interfaz lo llama «Docente invitado») y no se le puede cambiar; una
 * cuenta con código SIS nunca puede tenerlo.
 */
class RoleNotAllowedForAccountException extends Exception
{
    public static function guestAccount(): self
    {
        return new self('Una cuenta de docente invitado solo puede tener el rol Docente invitado.');
    }

    public static function sisAccount(): self
    {
        return new self('El rol Docente invitado es solo para cuentas de docente invitado, sin código SIS.');
    }

    public static function guestRoleLocked(): self
    {
        return new self('El rol de docente invitado no se puede cambiar.');
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Los datos proporcionados no son válidos.',
            'errors'  => ['id_rol' => [$this->getMessage()]],
        ], 422);
    }
}
