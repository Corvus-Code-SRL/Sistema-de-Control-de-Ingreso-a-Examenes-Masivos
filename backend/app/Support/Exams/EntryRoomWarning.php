<?php

namespace App\Support\Exams;

/** Avisos de ambiente del control de ingreso: informan y dejan registrar con confirmación. */
final class EntryRoomWarning
{
    public const VERDICT = 'AVISO_AMBIENTE';
    public const OUT_OF_ROOM = 'FUERA_DE_AMBIENTE';
    public const ROOM_FULL = 'AMBIENTE_LLENO';

    public const CONFIRM_MESSAGE = 'Confirme el aviso de ambiente para registrar al estudiante.';
    public const CONFIRM_FIELD_MESSAGE = 'La confirmación del aviso de ambiente no es válida.';
    public const OUT_OF_ROOM_MESSAGE = 'Este estudiante tiene asignado el ambiente %s';
    public const ROOM_FULL_MESSAGE = 'El ambiente %s ya está lleno (%d de %d)';
}
