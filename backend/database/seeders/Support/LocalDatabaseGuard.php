<?php

namespace Database\Seeders\Support;

use RuntimeException;

/**
 * Los seeders con ids fijos (TestData) solo pueden escribir en una base local.
 *
 * Sus upserts por clave primaria pisan filas existentes y no se pueden deshacer, así que contra la
 * base compartida (Supabase) harían daño. Antes de escribir nada se comprueba el host de la
 * conexión por defecto: solo se acepta postgres (el servicio de Docker), 127.0.0.1 o localhost.
 */
final class LocalDatabaseGuard
{
    private const LOCAL_HOSTS = ['postgres', '127.0.0.1', 'localhost'];

    public static function assertLocal(string $seeder): void
    {
        $connection = (string) config('database.default');
        $host = config("database.connections.{$connection}.host");

        if (is_string($host) && in_array(strtolower(trim($host)), self::LOCAL_HOSTS, true)) {
            return;
        }

        throw new RuntimeException(sprintf(
            '%s escribe ids fijos y solo se ejecuta contra una base local (DB_HOST: %s); '
            . 'la conexión «%s» apunta a «%s». No se escribió nada.',
            $seeder,
            implode(', ', self::LOCAL_HOSTS),
            $connection,
            is_string($host) ? $host : 'un host no válido'
        ));
    }
}
