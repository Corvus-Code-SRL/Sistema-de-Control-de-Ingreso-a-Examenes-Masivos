<?php

namespace App\Services\RiskCenter\Academic;

use Carbon\Carbon;

/**
 * Estado del docente que aprobó un reporte, tal como lo resuelve {@see ApprovingTeacherStatusService}.
 *
 * `inactiveSince` y `source` son nulos si el docente está activo o si su inactividad todavía no tiene fecha
 * (nadie intentó rectificar uno de sus registros, así que aún no se detectó). `eligibleFrom` es la fecha desde
 * la que el Administrador puede rectificar sin su confirmación.
 */
class ApprovingTeacherStatus
{
    public const SOURCE_ROLE_CLOSED = 'ROL_CERRADO';

    public const SOURCE_FIRST_DETECTION = 'PRIMERA_DETECCION';

    public bool $active;

    public ?Carbon $inactiveSince;

    public ?string $source;

    public ?Carbon $eligibleFrom;

    public function __construct(bool $active, ?Carbon $inactiveSince, ?string $source, ?Carbon $eligibleFrom)
    {
        $this->active = $active;
        $this->inactiveSince = $inactiveSince;
        $this->source = $source;
        $this->eligibleFrom = $eligibleFrom;
    }

    /** ¿Ya pasó el plazo para rectificar sin su confirmación? Un docente activo nunca lo cumple. */
    public function canVoidWithoutConfirmation(?Carbon $now = null): bool
    {
        return ! $this->active
            && $this->eligibleFrom !== null
            && $this->eligibleFrom->lessThanOrEqualTo($now ?? Carbon::now());
    }
}
