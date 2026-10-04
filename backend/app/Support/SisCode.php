<?php

namespace App\Support;

/**
 * Forma canónica del código SIS, común a usuario.cod_sis y estudiante.cod_sis.
 *
 * El SIS es la clave que une cuentas con filas de la nómina (por ejemplo, para impedir que
 * un estudiante sea habilitado como auxiliar de su propio examen): si cada lado lo
 * normalizara distinto, esa comparación fallaría sin avisar. Por eso se decide aquí, una vez.
 *
 * Solo recorta, pasa a mayúsculas y colapsa los espacios internos. No quita ceros a la
 * izquierda ni exige dígitos ni una longitud: conviven 5 dígitos (docentes), 9 dígitos
 * (auxiliares y estudiantes) y alfanuméricos (Administrador). Esas reglas, si existen, son
 * de cada flujo (p. ej. la carga de nómina) y se evalúan sobre el valor ya normalizado.
 */
final class SisCode
{
    public static function normalize(string $code): string
    {
        // \p{Z} cubre el espacio duro (U+00A0) que Excel deja en las celdas copiadas.
        $collapsed = preg_replace('/[\s\p{Z}]+/u', ' ', $code);

        return mb_strtoupper(trim($collapsed ?? $code, " \t\n\r\0\x0B"));
    }

    public static function normalizeNullable(?string $code): ?string
    {
        if ($code === null) {
            return null;
        }

        $normalized = self::normalize($code);

        return $normalized === '' ? null : $normalized;
    }
}
