<?php

namespace App\Console\Commands;

use App\Exceptions\Maintenance\DatabaseBackupException;
use App\Services\Maintenance\DatabaseBackupService;
use Illuminate\Console\Command;

/** php artisan sciem:respaldar — guarda un pg_dump de la base y aplica la retención. */
class BackupDatabaseCommand extends Command
{
    public const PRIVACY_WARNING = 'El respaldo contiene datos personales reales (nombres, códigos SIS, CI): '
        . 'no lo suba a git ni a una carpeta compartida, ni lo envíe por chat.';

    protected $signature = 'sciem:respaldar';

    protected $description = 'Guarda un respaldo (pg_dump) de la base y deja solo los más recientes';

    public function handle(DatabaseBackupService $backups): int
    {
        try {
            $file = $backups->backup();
        } catch (DatabaseBackupException $exception) {
            // Señal operativa: el respaldo diario corre sin nadie mirando, así queda en el log.
            report($exception);
            $this->error('No se pudo respaldar la base. ' . $exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Respaldo guardado: ' . $file . ' (' . $this->size((int) filesize($file)) . ').');
        $this->line('Respaldos guardados: ' . count($backups->backups()) . '.');
        $this->warn(self::PRIVACY_WARNING);

        return self::SUCCESS;
    }

    private function size(int $bytes): string
    {
        return $bytes >= 1048576
            ? number_format($bytes / 1048576, 1) . ' MB'
            : number_format($bytes / 1024, 1) . ' KB';
    }
}
