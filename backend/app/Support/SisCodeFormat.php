<?php

namespace App\Support;

use App\Models\Role;

/**
 * Formato del código SIS de una cuenta según el rol que va a recibir (HU-001).
 *
 * Opera sobre el valor ya normalizado (ver SisCode). Los códigos de la nómina de estudiantes
 * tienen su propia regla (config sciem.estudiante_cod_sis): esta es solo de las cuentas.
 *
 * - Auxiliar: 9 dígitos cuyos 4 primeros son un año entre 1990 y el actual, o 8 dígitos del 90 al 99.
 * - Administrador: prefijo de letras seguido de dígitos (ADM0001, ADM13352440).
 * - Docente: alfanumérico de 3 a 15 caracteres, sin formato fijo (existen códigos numéricos como 10452).
 * - Sin rol: cualquiera de los anteriores.
 *
 * Ningún código puede empezar con INV: ese prefijo es de los docentes invitados, cuyo código lo
 * genera el sistema.
 */
final class SisCodeFormat
{
    private const FIRST_YEAR = 1990;

    private const MAX_LENGTH = 15;

    public static function isValid(string $code, ?string $roleName): bool
    {
        if ($code === '' || strlen($code) > self::MAX_LENGTH || SisCode::isGuest($code)) {
            return false;
        }

        switch ($roleName) {
            case Role::AUXILIAR:
                return self::isAssistantCode($code);
            case Role::ADMINISTRADOR:
                return self::isAdministratorCode($code);
            case Role::DOCENTE:
                return self::isTeacherCode($code);
            default:
                return self::isAssistantCode($code)
                    || self::isAdministratorCode($code)
                    || self::isTeacherCode($code);
        }
    }

    private static function isAssistantCode(string $code): bool
    {
        if (preg_match('/^9\d{7}$/', $code) === 1) {
            return true;
        }

        if (preg_match('/^(\d{4})\d{5}$/', $code, $matches) !== 1) {
            return false;
        }

        $year = (int) $matches[1];

        return $year >= self::FIRST_YEAR && $year <= (int) now()->year;
    }

    private static function isAdministratorCode(string $code): bool
    {
        return preg_match('/^[A-Z]+\d+$/', $code) === 1;
    }

    private static function isTeacherCode(string $code): bool
    {
        return preg_match('/^[A-Z0-9]{3,15}$/', $code) === 1;
    }
}
