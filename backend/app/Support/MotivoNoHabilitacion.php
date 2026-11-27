<?php

namespace App\Support;

/**
 * Motivos de NO_HABILITADO guardados en examen_estudiante.observacion (HU-028).
 *
 * El valor se serializa como "<CODIGO>" o "<CODIGO>: <detalle>". Desde que la
 * lista solo se carga por archivo, el sistema escribe únicamente
 * NO_FIGURA_EN_LISTA; los motivos manuales (all()) ya no se pueden elegir, pero
 * las filas guardadas antes se siguen leyendo con fromObservation().
 */
final class MotivoNoHabilitacion
{
    public const ASISTENCIA_INSUFICIENTE  = 'ASISTENCIA_INSUFICIENTE';
    public const NO_ENTREGO_TRABAJO_FINAL = 'NO_ENTREGO_TRABAJO_FINAL';
    public const NO_CUMPLE_PRERREQUISITO  = 'NO_CUMPLE_PRERREQUISITO';
    public const OTRO                     = 'OTRO';

    /**
     * Motivo fijo que el sistema asigna al reemplazar la lista con un archivo.
     * No forma parte de all(): el docente no lo elige a mano.
     */
    public const NO_FIGURA_EN_LISTA       = 'NO_FIGURA_EN_LISTA';

    /** @return array<int, string> motivos manuales históricos, solo para leer filas antiguas */
    public static function all(): array
    {
        return [
            self::ASISTENCIA_INSUFICIENTE,
            self::NO_ENTREGO_TRABAJO_FINAL,
            self::NO_CUMPLE_PRERREQUISITO,
            self::OTRO,
        ];
    }

    /**
     * Deserializa examen_estudiante.observacion en sus partes.
     * Si no empieza con un código válido, devuelve null (fila con
     * observación que no puso HU-028: se ignora en el Resource).
     *
     * @return array{motivo: string, detalle: ?string}|null
     */
    public static function fromObservation(?string $observation): ?array
    {
        if ($observation === null || $observation === '') {
            return null;
        }

        foreach ([...self::all(), self::NO_FIGURA_EN_LISTA] as $motivo) {
            if ($observation === $motivo) {
                return ['motivo' => $motivo, 'detalle' => null];
            }

            $prefix = $motivo . ': ';

            if (strncmp($observation, $prefix, strlen($prefix)) === 0) {
                return [
                    'motivo'  => $motivo,
                    'detalle' => substr($observation, strlen($prefix)),
                ];
            }
        }

        return null;
    }

    /** Etiqueta legible del motivo, para mostrarla en mensajes al usuario. */
    public static function label(string $motivo): string
    {
        return match ($motivo) {
            self::ASISTENCIA_INSUFICIENTE => 'Asistencia insuficiente',
            self::NO_ENTREGO_TRABAJO_FINAL => 'No entregó el trabajo final',
            self::NO_CUMPLE_PRERREQUISITO => 'No cumple prerrequisito',
            self::OTRO => 'Otro',
            self::NO_FIGURA_EN_LISTA => 'No figura en la lista cargada',
            default => $motivo,
        };
    }
}
