<?php

namespace App\Support;

/**
 * Cuentas de sistema: firman la bitácora de las automatizaciones (seeders, scheduler) y no
 * pertenecen a ninguna persona, así que nunca pueden iniciar sesión, sea cual sea su rol.
 *
 * Se identifican por el código listado en config('sciem.cuentas_sistema'), no por su id: en los
 * datos de prueba el Administrador ADM0001 comparte id con la cuenta de sistema y sí debe poder
 * ingresar. Reemplazar la cuenta (p. ej. por ADM0000X) es cambiar esa lista.
 */
final class SystemAccounts
{
    public static function isSystemCode(string $code): bool
    {
        return in_array(SisCode::normalize($code), self::codes(), true);
    }

    /** @return array<int, string> */
    private static function codes(): array
    {
        return array_map(
            [SisCode::class, 'normalize'],
            array_filter((array) config('sciem.cuentas_sistema', []), 'is_string')
        );
    }
}
