<?php

namespace App\Console\Commands;

use App\Services\Maintenance\DatabaseHealthService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * php artisan sciem:salud-bd — estado de la base en vivo (RNF-14). Solo lectura: no cierra sesiones
 * ni cambia nada. Pensado para mirarlo durante un examen masivo.
 */
class DatabaseHealthCommand extends Command
{
    /** Desde este porcentaje de max_connections se avisa. */
    public const CONNECTIONS_WARNING_PERCENT = 80;

    protected $signature = 'sciem:salud-bd
        {--umbral=30 : Segundos desde los que una consulta o transacción se considera larga}';

    protected $description = 'Muestra sesiones, bloqueos y filas muertas de la base (solo lectura)';

    public function handle(DatabaseHealthService $health): int
    {
        $threshold = max(0, (int) $this->option('umbral'));

        $this->connections($health);
        $this->sessions($health);
        $this->longSessions($health, $threshold);
        $this->blockedSessions($health);
        $this->deadRows($health);

        return self::SUCCESS;
    }

    private function connections(DatabaseHealthService $health): void
    {
        $connections = $health->connections();
        $percent = $connections['maximo'] > 0
            ? (int) round($connections['usadas'] * 100 / $connections['maximo'])
            : 0;
        $line = "Conexiones: {$connections['usadas']} de {$connections['maximo']} ({$percent} %).";

        $percent >= self::CONNECTIONS_WARNING_PERCENT ? $this->warn($line) : $this->info($line);
    }

    private function sessions(DatabaseHealthService $health): void
    {
        $this->newLine();
        $this->line('Sesiones de esta base por aplicación y estado:');
        $rows = $health->sessionsByApplication();

        $rows === []
            ? $this->line('  (ninguna además de esta)')
            : $this->table(['Aplicación', 'Estado', 'Sesiones'], $rows);
    }

    private function longSessions(DatabaseHealthService $health, int $threshold): void
    {
        $this->newLine();
        $this->line("Consultas o transacciones abiertas hace {$threshold} s o más:");
        $rows = $health->longSessions($threshold);

        if ($rows === []) {
            $this->line('  (ninguna)');

            return;
        }

        $this->warn('  Una transacción abierta y quieta retiene locks y frena la limpieza.');
        $this->table(['PID', 'Aplicación', 'Estado', 'Segundos', 'Consulta'], $rows);
    }

    private function blockedSessions(DatabaseHealthService $health): void
    {
        $this->newLine();
        $this->line('Sesiones esperando un lock:');
        $rows = $health->blockedSessions();

        if ($rows === []) {
            $this->line('  (ninguna)');

            return;
        }

        $this->warn('  Hay sesiones bloqueadas por otras (columna «Bloqueada por»).');
        $this->table(['PID', 'Aplicación', 'Bloqueada por', 'Segundos', 'Consulta'], $rows);
    }

    private function deadRows(DatabaseHealthService $health): void
    {
        $this->newLine();
        $this->line('Tablas con más filas muertas (las limpia el autovacuum):');

        $rows = array_map(fn (array $row) => [
            $row['tabla'],
            $row['vivas'],
            $row['muertas'],
            $this->formatDate($row['ultimo_autovacuum']),
            $this->formatDate($row['ultimo_autoanalyze']),
        ], $health->deadRows());

        $this->table(['Tabla', 'Vivas', 'Muertas', 'Último autovacuum', 'Último autoanalyze'], $rows);
    }

    private function formatDate(?string $timestamp): string
    {
        return $timestamp === null
            ? '—'
            : Carbon::parse($timestamp)->setTimezone((string) config('sciem.zona_horaria'))->format('d/m/Y H:i');
    }
}
