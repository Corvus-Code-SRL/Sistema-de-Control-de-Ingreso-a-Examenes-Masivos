<?php

namespace App\Support;

final class ExamSituationType
{
    public const ROOM_CHANGE = 'CAMBIO_AMBIENTE';
    public const IDENTIFICATION = 'IDENTIFICACION';

    public static function values(): array 
    {
        return [self::ROOM_CHANGE, self::IDENTIFICATION];
    }
}