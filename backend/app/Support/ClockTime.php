<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Hora de reloj HH:MM que muestran las respuestas del control de ingreso. La base guarda
 * hora_ingreso como H:i:s local; los instantes (timestamptz) se pasan a la zona del sistema.
 */
final class ClockTime
{
    public const FORMAT = 'H:i';

    /** De un `time` de la base (H:i:s) a HH:MM. */
    public static function fromTime(string $time): string
    {
        return Carbon::createFromFormat('H:i:s', $time)->format(self::FORMAT);
    }

    /** De un instante a HH:MM en la zona horaria del sistema. */
    public static function local(CarbonInterface $at): string
    {
        return $at->copy()->setTimezone(config('sciem.zona_horaria'))->format(self::FORMAT);
    }
}
