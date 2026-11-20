<?php

namespace App\Support\Exams;

final class DistributionMessages
{
    public const SAVED = 'Distribución guardada';
    public const NO_PERMISSION = 'No tiene permiso para distribuir habilitados.';
    public const COLLABORATOR_READ_ONLY = 'Solo el docente que creó el examen puede guardar la distribución.';
    public const MULTIPLE_ROOMS_REQUIRED = 'La distribución requiere al menos dos ambientes.';
    public const SINGLE_ROOM = 'Todos los habilitados quedan en el ambiente %s.';
    public const SINGLE_ROOM_INSUFFICIENT =
        'El ambiente %s tiene capacidad para %d de %d habilitados. Faltan %d lugares.';
    public const NO_ROOMS = 'El examen no tiene ambientes asignados.';
    public const ROOM_FULL = 'El ambiente ya está lleno';
    public const MISSING_SEATS = 'Faltan %d lugares para asignar a todos los habilitados.';
    public const INCOMPLETE_ROSTER = 'Debe asignar exactamente una vez a cada estudiante habilitado del examen.';
    public const FOREIGN_ROOM = 'Uno o más ambientes no pertenecen al examen.';
    public const STALE = 'La distribución cambió. Recargue la distribución antes de guardar.';
    public const INVALID_VERSION = 'Debe indicar una versión de distribución válida.';
    public const ASSIGNMENTS_REQUIRED = 'Debe enviar la lista completa de asignaciones, aunque esté vacía.';
    public const INVALID_ASSIGNMENTS = 'Las asignaciones deben enviarse como una lista.';
    public const INVALID_STUDENT = 'Cada asignación debe indicar un estudiante válido.';
    public const DUPLICATE_STUDENT = 'Un estudiante no puede repetirse en la distribución.';
    public const INVALID_ROOM = 'Cada asignación debe indicar un ambiente válido.';
}
