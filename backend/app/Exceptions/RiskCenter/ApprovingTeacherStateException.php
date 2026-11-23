<?php

namespace App\Exceptions\RiskCenter;

use App\Support\ApiResponse;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * El estado del docente que aprobó el registro no permite la operación pedida (HU-062): no se le puede enviar una
 * solicitud si está inactivo, y no se rectifica sin su confirmación si sigue activo o si aún no pasó el plazo.
 * Responde 409.
 */
class ApprovingTeacherStateException extends Exception
{
    /** Inactivo y todavía dentro del plazo: se informa desde cuándo se podrá rectificar sin su confirmación. */
    public static function inactiveUntil(Carbon $eligibleFrom): self
    {
        return new self(sprintf(
            'El docente que aprobó este registro ya no está activo. Podrá rectificarlo sin su confirmación a '
            . 'partir del %s.',
            $eligibleFrom->copy()->timezone((string) config('sciem.zona_horaria', 'America/La_Paz'))->format('d/m/Y')
        ));
    }

    /** Inactivo y con el plazo cumplido: ya no tiene sentido enviarle una solicitud. */
    public static function inactiveCanVoidDirectly(): self
    {
        return new self(
            'El docente que aprobó este registro ya no está activo, así que no se le puede enviar una solicitud. '
            . 'Rectifique el registro sin su confirmación.'
        );
    }

    /** Sigue activo: la rectificación necesita su confirmación. */
    public static function stillActive(): self
    {
        return new self(
            'El docente que aprobó este registro sigue activo: la rectificación necesita su confirmación. '
            . 'Envíele una solicitud.'
        );
    }

    public function render(Request $request): JsonResponse
    {
        return ApiResponse::error($this->getMessage(), 409);
    }
}
