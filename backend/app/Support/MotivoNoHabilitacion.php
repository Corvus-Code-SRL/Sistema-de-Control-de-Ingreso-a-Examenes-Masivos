<?php

namespace App\Support;

/**
 * Motivos por los que un docente deja a un estudiante como NO_HABILITADO
 * en la lista de habilitados de un examen (HU-028).
 *
 * El valor se serializa en examen_estudiante.observacion como
 * "<CODIGO>" o "<CODIGO>: <detalle>" (solo si el motivo lo requiere).
 *
 * Los motivos son un catálogo corto y cerrado: el docente elige uno de
 * los existentes. "OTRO" exige detalle libre.
 */
final class MotivoNoHabilitacion
{
    public const ASISTENCIA_INSUFICIENTE  = 'ASISTENCIA_INSUFICIENTE';
    public const NO_ENTREGO_TRABAJO_FINAL = 'NO_ENTREGO_TRABAJO_FINAL';
    public const NO_CUMPLE_PRERREQUISITO  = 'NO_CUMPLE_PRERREQUISITO';
    public const OTRO                     = 'OTRO';

    /** @return array<int, string> */
    public static function all(): array
    {
        return [
            self::ASISTENCIA_INSUFICIENTE,
            self::NO_ENTREGO_TRABAJO_FINAL,
            self::NO_CUMPLE_PRERREQUISITO,
            self::OTRO,
        ];
    }

    public static function isValid(?string $value): bool
    {
        return in_array($value, self::all(), true);
    }

    /** "OTRO" exige detalle libre en la observación. */
    public static function requiresDetail(?string $value): bool
    {
        return $value === self::OTRO;
    }

    /**
     * Serializa el motivo en el formato de examen_estudiante.observacion.
     * Si el motivo requiere detalle y el detalle es nulo o vacío, lanza
     * InvalidArgumentException (el FormRequest ya lo valida; aquí se
     * defiende el Service).
     *
     * Si el motivo no requiere detalle, el detalle se descarta aunque
     * venga: no se concatena. Esto es intencional y se documenta en
     * MotivoNoHabilitacionTest.
     */
    public static function toObservation(string $motivo, ?string $detalle): string
    {
        if (! self::isValid($motivo)) {
            throw new \InvalidArgumentException(
                "El motivo '{$motivo}' no es válido."
            );
        }

        $detail = $detalle !== null ? trim($detalle) : null;

        if ($detail !== null && str_starts_with($detail, ':')) {
            throw new \InvalidArgumentException(
                'El detalle no puede empezar con ":".'
            );
        }

        if (self::requiresDetail($motivo)) {
            if ($detail === null || $detail === '') {
                throw new \InvalidArgumentException(
                    'El motivo OTRO exige un detalle.'
                );
            }

            return $motivo . ': ' . $detail;
        }

        return $motivo;
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

        foreach (self::all() as $motivo) {
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
            default => $motivo,
        };
    }
}
