<?php

namespace App\Exceptions\Academic;

use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * El período activo no está configurado (SCIEM_PERIODO_ACTIVO_ID).
 *
 * Es un error de despliegue, no de la solicitud: falla temprano y con un mensaje
 * claro en vez de crear grupos con un id_periodo inválido.
 */
class ActivePeriodNotConfiguredException extends RuntimeException
{
    protected $message = 'No hay un período académico activo configurado. '
        . 'Defina SCIEM_PERIODO_ACTIVO_ID en el entorno del servidor.';

    public function render(Request $request): JsonResponse
    {
        return ApiResponse::error($this->getMessage(), 500);
    }
}
