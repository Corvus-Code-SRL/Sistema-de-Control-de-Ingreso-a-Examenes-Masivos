<?php

namespace App\Support;

/**
 * Valores del enum public.estado_registro.
 *
 * PHP 8.0 no admite enums nativos, por eso se declaran como constantes.
 */
final class RecordStatus
{
    public const ACTIVE = 'ACTIVO';

    public const INACTIVE = 'INACTIVO';
}
