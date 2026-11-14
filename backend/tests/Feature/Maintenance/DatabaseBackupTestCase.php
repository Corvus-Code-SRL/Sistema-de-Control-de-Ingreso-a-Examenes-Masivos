<?php

namespace Tests\Feature\Maintenance;

use App\Support\Process\ExternalProcess;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Base de las pruebas de respaldo y restauración: un directorio temporal de respaldos, pg_dump / pg_restore
 * simulados (no se ejecuta ningún programa ni se toca la base) y la restauración permitida.
 */
abstract class DatabaseBackupTestCase extends TestCase
{
    protected string $directory;

    protected FakeExternalProcess $process;

    /** @var array<int, string> */
    private array $temporary = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = $this->temporaryDirectory('sciem_respaldos_');

        config([
            'sciem.respaldo.directorio' => $this->directory,
            'sciem.respaldo.retencion' => 7,
            'sciem.respaldo.pg_dump' => 'pg_dump',
            'sciem.respaldo.pg_restore' => 'pg_restore',
            'sciem.respaldo.restaurar_permitido' => true,
        ]);

        $this->process = new FakeExternalProcess();
        $this->app->instance(ExternalProcess::class, $this->process);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        foreach ($this->temporary as $path) {
            $this->removeTree($path);
        }

        parent::tearDown();
    }

    protected function temporaryDirectory(string $prefix): string
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $prefix . bin2hex(random_bytes(4));
        mkdir($path, 0700, true);
        $this->temporary[] = $path;

        return $path;
    }

    /** @return array<int, string> nombres de archivo, ordenados */
    protected function files(): array
    {
        $files = array_values(array_diff(scandir($this->directory) ?: [], ['.', '..']));
        sort($files);

        return $files;
    }

    protected function oldBackup(string $stamp): void
    {
        file_put_contents($this->directory . '/sciem_' . $stamp . '.dump', 'viejo');
    }

    /** Apunta la conexión por defecto a otro host sin abrirla: solo cambia lo que ven los comandos. */
    protected function useHost(string $host): void
    {
        $default = config('database.default');

        config([
            'database.connections.respaldo_prueba' => array_merge(
                config('database.connections.' . $default),
                ['host' => $host]
            ),
            'database.default' => 'respaldo_prueba',
        ]);
    }

    private function removeTree(string $path): void
    {
        if (! file_exists($path)) {
            return;
        }

        if (is_file($path) || is_link($path)) {
            unlink($path);

            return;
        }

        foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $name) {
            $this->removeTree($path . DIRECTORY_SEPARATOR . $name);
        }

        rmdir($path);
    }
}
