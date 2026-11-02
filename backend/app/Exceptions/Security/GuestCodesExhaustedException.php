<?php

namespace App\Exceptions\Security;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ya están en uso las 9000 combinaciones de códigos de docente invitado (INV1000 a INV9999).
 */
class GuestCodesExhaustedException extends Exception
{
    protected $message = 'No quedan códigos disponibles para docentes invitados. Deshabilite cuentas de invitado que ya no se usen o contacte a soporte.';

    public function render(Request $request): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 422);
    }
}
