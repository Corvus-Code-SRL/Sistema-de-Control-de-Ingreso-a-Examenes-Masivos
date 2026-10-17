<?php

namespace App\Support;

/**
 * Valores del enum public.estado_reporte (reporte_estudiante.estado y reporte_postulante.estado).
 *
 * PHP 8.0 no admite enums nativos, por eso se declaran como constantes.
 */
final class ReportStatus
{
    public const PENDING = 'PENDIENTE';

    public const APPROVED = 'APROBADO';

    public const REJECTED = 'RECHAZADO';
}
