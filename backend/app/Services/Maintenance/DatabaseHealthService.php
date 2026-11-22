<?php

namespace App\Services\Maintenance;

use Illuminate\Support\Facades\DB;

/**
 * Lee el estado de la base desde las vistas de estadísticas de PostgreSQL (RNF-14). Solo lectura.
 *
 * - pg_stat_activity: una fila por sesión (cada petición de Laravel abre la suya).
 * - pg_stat_user_tables: filas vivas y muertas por tabla, y cuándo pasó el autovacuum.
 *
 * La sesión que hace la consulta (pg_backend_pid) no se cuenta: es la del propio comando.
 */
class DatabaseHealthService
{
    /** Máximo de filas por sección del informe. */
    public const LIMIT = 20;

    /**
     * Sesiones de clientes abiertas contra el servidor y el máximo permitido.
     *
     * @return array{usadas: int, maximo: int}
     */
    public function connections(): array
    {
        $row = DB::selectOne(
            "select count(*) as usadas, current_setting('max_connections')::int as maximo
               from pg_stat_activity
              where backend_type = 'client backend'"
        );

        return ['usadas' => (int) $row->usadas, 'maximo' => (int) $row->maximo];
    }

    /**
     * Sesiones de esta base agrupadas por aplicación (sciem-web, sciem-consola, pgAdmin…) y estado.
     *
     * @return list<array{aplicacion: string, estado: string, sesiones: int}>
     */
    public function sessionsByApplication(): array
    {
        $rows = DB::select(
            "select coalesce(nullif(application_name, ''), '(sin nombre)') as aplicacion,
                    coalesce(state, '(sin estado)') as estado,
                    count(*) as sesiones
               from pg_stat_activity
              where datname = current_database()
                and backend_type = 'client backend'
                and pid <> pg_backend_pid()
              group by 1, 2
              order by 1, 2"
        );

        return array_map(fn ($row) => [
            'aplicacion' => (string) $row->aplicacion,
            'estado' => (string) $row->estado,
            'sesiones' => (int) $row->sesiones,
        ], $rows);
    }

    /**
     * Sesiones con una consulta o una transacción abierta hace al menos $seconds segundos. Una
     * transacción abierta y quieta («idle in transaction») retiene locks y frena al autovacuum.
     *
     * @return list<array{pid: int, aplicacion: string, estado: string, segundos: int, consulta: string}>
     */
    public function longSessions(int $seconds): array
    {
        $rows = DB::select(
            "select pid,
                    coalesce(nullif(application_name, ''), '(sin nombre)') as aplicacion,
                    state as estado,
                    floor(extract(epoch from now() - coalesce(xact_start, query_start)))::int as segundos,
                    left(regexp_replace(coalesce(query, ''), '\\s+', ' ', 'g'), 80) as consulta
               from pg_stat_activity
              where datname = current_database()
                and backend_type = 'client backend'
                and pid <> pg_backend_pid()
                and state in ('active', 'idle in transaction', 'idle in transaction (aborted)')
                and now() - coalesce(xact_start, query_start) >= make_interval(secs => ?)
              order by segundos desc
              limit " . self::LIMIT,
            [max(0, $seconds)]
        );

        return array_map(fn ($row) => [
            'pid' => (int) $row->pid,
            'aplicacion' => (string) $row->aplicacion,
            'estado' => (string) $row->estado,
            'segundos' => (int) $row->segundos,
            'consulta' => (string) $row->consulta,
        ], $rows);
    }

    /**
     * Sesiones esperando un lock que tiene otra sesión (por ejemplo, dos lockForUpdate sobre el mismo
     * examen).
     *
     * @return list<array{pid: int, aplicacion: string, bloqueada_por: string, segundos: int, consulta: string}>
     */
    public function blockedSessions(): array
    {
        $rows = DB::select(
            "select pid,
                    coalesce(nullif(application_name, ''), '(sin nombre)') as aplicacion,
                    array_to_string(pg_blocking_pids(pid), ', ') as bloqueada_por,
                    floor(extract(epoch from now() - coalesce(query_start, now())))::int as segundos,
                    left(regexp_replace(coalesce(query, ''), '\\s+', ' ', 'g'), 80) as consulta
               from pg_stat_activity
              where datname = current_database()
                and pid <> pg_backend_pid()
                and cardinality(pg_blocking_pids(pid)) > 0
              order by segundos desc
              limit " . self::LIMIT
        );

        return array_map(fn ($row) => [
            'pid' => (int) $row->pid,
            'aplicacion' => (string) $row->aplicacion,
            'bloqueada_por' => (string) $row->bloqueada_por,
            'segundos' => (int) $row->segundos,
            'consulta' => (string) $row->consulta,
        ], $rows);
    }

    /**
     * Tablas con más filas muertas y cuándo las limpió y analizó el autovacuum.
     *
     * @return list<array{tabla: string, vivas: int, muertas: int,
     *     ultimo_autovacuum: ?string, ultimo_autoanalyze: ?string}>
     */
    public function deadRows(int $limit = 10): array
    {
        $rows = DB::select(
            "select relname as tabla, n_live_tup as vivas, n_dead_tup as muertas,
                    last_autovacuum as ultimo_autovacuum, last_autoanalyze as ultimo_autoanalyze
               from pg_stat_user_tables
              where schemaname = current_schema()
              order by n_dead_tup desc, relname
              limit ?",
            [max(1, $limit)]
        );

        return array_map(fn ($row) => [
            'tabla' => (string) $row->tabla,
            'vivas' => (int) $row->vivas,
            'muertas' => (int) $row->muertas,
            'ultimo_autovacuum' => $row->ultimo_autovacuum,
            'ultimo_autoanalyze' => $row->ultimo_autoanalyze,
        ], $rows);
    }
}
