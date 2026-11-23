<?php

namespace App\Support\Database;

use Illuminate\Database\Connectors\PostgresConnector;
use PDO;

/**
 * Conector de PostgreSQL de SCIEM (RNF-14): completa dos cosas que el conector de Laravel 8 no hace: el
 * sufijo de origen en el nombre de aplicación y los límites de sesión. Laravel 8 sí pone application_name,
 * pero sin escapar el valor; por eso aquí se limpia.
 *
 * 1. Nombre de aplicación con origen: `sciem-web` o `sciem-consola`. Así, en pg_stat_activity se
 *    distinguen las peticiones de la SPA de las migraciones, los respaldos y el planificador.
 *
 * 2. Límites de sesión (milisegundos), para que un error no deje una sesión colgada:
 *    - statement_timeout: corta una consulta que tarda más del límite.
 *    - lock_timeout: deja de esperar un lock (lockForUpdate) pasado el límite.
 *    - idle_in_transaction_session_timeout: cierra una sesión que dejó una transacción abierta sin
 *      hacer nada; la base libera sus locks.
 *    Vacío o 0 significa sin límite. Por defecto solo se aplican a las peticiones web: una migración,
 *    un respaldo o un job del planificador pueden tardar legítimamente más.
 *
 * Laravel abre una conexión nueva por petición (ATTR_PERSISTENT en false), así que cada petición web
 * es una sesión nueva de PostgreSQL y recibe estos valores al conectarse.
 */
class SciemPostgresConnector extends PostgresConnector
{
    /** Clave de configuración => parámetro de PostgreSQL. */
    public const TIMEOUTS = [
        'statement_timeout' => 'statement_timeout',
        'lock_timeout' => 'lock_timeout',
        'idle_in_transaction_session_timeout' => 'idle_in_transaction_session_timeout',
    ];

    /** @var bool */
    private $runningInConsole;

    public function __construct(bool $runningInConsole)
    {
        $this->runningInConsole = $runningInConsole;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return PDO
     */
    public function connect(array $config)
    {
        $config['application_name'] = $this->applicationName($config);

        $connection = parent::connect($config);

        if ($this->appliesTimeouts($config)) {
            $this->configureSessionTimeouts($connection, $config);
        }

        return $connection;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function applicationName(array $config): string
    {
        $base = preg_replace('/[^a-z0-9_-]/i', '', (string) ($config['application_name'] ?? 'sciem'));

        return ($base !== '' ? $base : 'sciem') . ($this->runningInConsole ? '-consola' : '-web');
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function appliesTimeouts(array $config): bool
    {
        return ! $this->runningInConsole || (bool) ($config['session_timeouts_in_console'] ?? false);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function configureSessionTimeouts(PDO $connection, array $config): void
    {
        foreach (self::TIMEOUTS as $key => $parameter) {
            $milliseconds = (int) ($config[$key] ?? 0);

            if ($milliseconds > 0) {
                // Entero validado: no hay datos del usuario en la sentencia.
                $connection->exec(sprintf('SET %s = %d', $parameter, $milliseconds));
            }
        }
    }
}
