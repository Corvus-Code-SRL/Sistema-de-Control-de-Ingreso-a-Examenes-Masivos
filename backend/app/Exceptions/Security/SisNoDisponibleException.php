<?php

namespace App\Exceptions\Security;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SisNoDisponibleException extends Exception
{
    protected $message = 'El servicio institucional (SIS) no está disponible. Intente más tarde.';

    /**
     * Laravel llama este método solo. No hay que tocar el Handler.
     */
    public function render(Request $request): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 503);
    }
}