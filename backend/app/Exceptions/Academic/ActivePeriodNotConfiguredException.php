<?php

namespace App\Exceptions\Academic;

use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Ningún período académico está vigente (gestion_periodo.vigente).
 *
 * Es un error de despliegue, no de la solicitud: falla temprano y con un mensaje
 * claro en vez de crear grupos sin período.
 * Para fijar uno: docs/database/periodo-vigente.md.
 */
class ActivePeriodNotConfiguredException extends RuntimeException
{
    protected $message = 'No hay un período académico vigente. '
        . 'Un administrador del sistema debe fijar uno (gestion_periodo.vigente).';

    public function render(Request $request): JsonResponse
    {
        return ApiResponse::error($this->getMessage(), 500);
    }
}
