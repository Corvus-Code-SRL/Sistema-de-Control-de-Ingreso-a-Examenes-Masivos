<?php

namespace App\Console\Commands;

use App\Exceptions\Maintenance\DatabaseBackupException;
use App\Services\Maintenance\DatabaseBackupService;
use Illuminate\Console\Command;

/**
 * php artisan sciem:restaurar {archivo} — vuelve a cargar un respaldo sobre la base de la conexión actual.
 *
 * Reemplaza los datos actuales, así que está apagado salvo con SCIEM_RESTAURAR_PERMITIDO=true, guarda antes
 * un respaldo de cómo está la base (salvo con --sin-respaldo-previo) y pide escribir el host y el nombre de
 * la base. En producción exige además --force, que es lo único que salta esa confirmación. Sobre Supabase
 * (la base compartida de desarrollo) exige además --supabase. Sin archivo, lista los respaldos guardados.
 */
class RestoreDatabaseCommand extends Command
{
    protected $signature = 'sciem:restaurar
        {archivo? : Nombre del respaldo (del directorio de respaldos) o ruta completa}
        {--sin-respaldo-previo : No respaldar la base actual antes de restaurar}
        {--force : Necesario en producción; allí no pide confirmación}
        {--supabase : Autoriza restaurar sobre la base compartida de desarrollo en Supabase}';

    protected $description = 'Restaura un respaldo de la base (reemplaza los datos actuales)';

    public function handle(DatabaseBackupService $backups): int
    {
        $file = $this->argument('archivo');

        if ($file === null || $file === '') {
            return $this->listBackups($backups);
        }

        $file = (string) $file;

        try {
            $backups->assertRestoreAllowed();
        } catch (DatabaseBackupException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $production = $this->laravel->environment('production');

        if ($production && ! $this->option('force')) {
            $this->error('En producción la restauración exige --force.');

            return self::FAILURE;
        }

        $target = $backups->target();

        if ($backups->isSupabaseHost($target['host']) && ! $this->confirmSupabase($target['host'])) {
            return self::FAILURE;
        }

        try {
            $path = $backups->locate($file);
        } catch (DatabaseBackupException $exception) {
            $this->error('No se pudo restaurar. ' . $exception->getMessage());

            return self::FAILURE;
        }

        if (! ($production && $this->option('force')) && ! $this->confirmRestore($target, $file)) {
            return self::FAILURE;
        }

        try {
            if (! $this->option('sin-respaldo-previo')) {
                $this->info('Respaldo previo guardado: ' . $backups->backup([$path]) . '.');
                $this->warn(BackupDatabaseCommand::PRIVACY_WARNING);
            }

            $backups->restore($path);
        } catch (DatabaseBackupException $exception) {
            $this->error('No se pudo restaurar. ' . $exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Base restaurada desde ' . $file . '.');

        return self::SUCCESS;
    }

    private function confirmSupabase(string $host): bool
    {
        if (! $this->option('supabase')) {
            $this->error(
                'La base está en Supabase («' . $host . '»), la base compartida de desarrollo. '
                . 'Restaurar allí reemplaza los datos de todo el equipo: agregue --supabase si es lo que quiere.'
            );

            return false;
        }

        $this->warn('ATENCIÓN: esto reemplaza la base de desarrollo compartida en Supabase («' . $host . '»).');

        return true;
    }

    /**
     * El operador escribe el host y el nombre exactos de la base que se va a reemplazar y después confirma.
     *
     * @param  array{host: string, database: string}  $target
     */
    private function confirmRestore(array $target, string $file): bool
    {
        $host = $target['host'] !== '' ? $target['host'] : '(socket local)';

        $this->line('Se reemplazarán TODOS los datos de esta base:');
        $this->line('  Host: ' . $host);
        $this->line('  Base: ' . $target['database']);

        if (trim((string) $this->ask('Escriba el host de la base para continuar')) !== $host) {
            $this->line('Restauración cancelada: el host no coincide. La base no cambió.');

            return false;
        }

        if (trim((string) $this->ask('Escriba el nombre de la base para continuar')) !== $target['database']) {
            $this->line('Restauración cancelada: el nombre de la base no coincide. La base no cambió.');

            return false;
        }

        $question = 'Se reemplazarán los datos de la base «' . $target['database']
            . '» con «' . $file . '». ¿Continuar?';

        if (! $this->confirm($question)) {
            $this->line('Restauración cancelada. La base no cambió.');

            return false;
        }

        return true;
    }

    private function listBackups(DatabaseBackupService $backups): int
    {
        $files = $backups->backups();

        if ($files === []) {
            $this->warn('No hay respaldos en ' . $backups->directory() . '.');

            return self::FAILURE;
        }

        $this->line('Respaldos guardados (del más reciente al más antiguo):');

        foreach ($files as $path) {
            $this->line('  ' . basename($path));
        }

        $this->line('Indique uno: php artisan sciem:restaurar ' . basename($files[0]));

        return self::SUCCESS;
    }
}
