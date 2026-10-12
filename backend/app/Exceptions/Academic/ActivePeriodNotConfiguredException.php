<?php

namespace App\Exceptions\Academic;

use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Ningún período académico está activo (gestion_periodo.activo).
 *
 * Es un error de despliegue, no de la solicitud: falla temprano y con un mensaje
 * claro en vez de crear grupos sin período.
 * Para activar uno: docs/database/periodo-activo.md.
 */
class ActivePeriodNotConfiguredException extends RuntimeException
{
    protected $message = 'No hay un período académico activo. '
        . 'Un administrador del sistema debe activar uno (gestion_periodo.activo).';

    public function render(Request $request): JsonResponse
    {
        return ApiResponse::error($this->getMessage(), 500);
    }
}
