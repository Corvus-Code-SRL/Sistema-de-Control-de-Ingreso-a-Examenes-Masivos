<?php

namespace App\Services\Maintenance;

use App\Exceptions\Maintenance\DatabaseBackupException;
use App\Support\Process\ExternalProcess;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Respaldo y restauración de la base con pg_dump / pg_restore (docs/database/respaldo-y-restauracion.md).
 *
 * - Respalda solo el esquema de la aplicación (public): en Supabase los demás (auth, storage…) son de la
 *   plataforma. Formato custom: comprimido y restaurable con pg_restore.
 * - Los datos de conexión son los de la conexión por defecto de Laravel. La contraseña viaja por el entorno
 *   del proceso (PGPASSWORD), nunca en los argumentos ni en los mensajes.
 * - Escribe primero un archivo «.parcial» y lo renombra al terminar: un respaldo cortado a la mitad nunca
 *   queda con el nombre de uno bueno. Después deja solo los `retencion` más recientes, sin borrar nunca el
 *   que acaba de crear ni los que se le indiquen como protegidos.
 * - Al restaurar no toca el esquema public en sí (solo su contenido): borrarlo arrastraría las extensiones
 *   que viven en él (pgcrypto) y en Supabase no es nuestro.
 * - Restaurar está apagado salvo que SCIEM_RESTAURAR_PERMITIDO sea true.
 * - El directorio de respaldos nunca puede quedar dentro de una copia de git (salvo storage/app, ignorado):
 *   los .dump tienen datos personales reales.
 */
class DatabaseBackupService
{
    public const PREFIX = 'sciem_';

    public const EXTENSION = '.dump';

    private const RENAME_ATTEMPTS = 3;

    private ExternalProcess $process;

    public function __construct(ExternalProcess $process)
    {
        $this->process = $process;
    }

    /**
     * Genera un respaldo y devuelve su ruta.
     *
     * @param  array<int, string>  $protected  Rutas que la retención no debe borrar (además del nuevo respaldo).
     * @throws DatabaseBackupException
     */
    public function backup(array $protected = []): string
    {
        $directory = $this->directory();
        $this->ensureDirectory($directory);

        $file = $this->uniquePath($directory);
        $partial = $file . '.parcial';

        $result = $this->process->run([
            $this->pgDump(),
            '--format=custom',
            '--no-owner',
            '--no-privileges',
            '--schema=' . $this->schema(),
            '--file=' . $partial,
        ], $this->connectionEnv(), $this->timeout());

        if (! $result->successful() || ! is_file($partial) || filesize($partial) === 0) {
            $this->remove($partial);

            throw new DatabaseBackupException($this->failure('pg_dump', $result->exitCode, $result->errorOutput));
        }

        if (! $this->moveInto($partial, $file)) {
            $this->remove($partial);

            throw new DatabaseBackupException('No se pudo guardar el respaldo en ' . $file . '.');
        }

        $this->prune(array_merge([$file], $protected));

        return $file;
    }

    /**
     * Carga un respaldo sobre la base de la conexión por defecto, en una sola transacción: si algo falla,
     * la base queda como estaba.
     *
     * @throws DatabaseBackupException
     */
    public function restore(string $file): void
    {
        $this->assertRestoreAllowed();

        $path = $this->locate($file);
        $list = $this->restoreList($path);

        try {
            $result = $this->process->run([
                $this->pgRestore(),
                '--clean',
                '--if-exists',
                '--no-owner',
                '--no-privileges',
                '--single-transaction',
                '--exit-on-error',
                '--use-list=' . $list,
                '--dbname=' . $this->database(),
                $path,
            ], $this->connectionEnv(), $this->timeout());
        } finally {
            $this->remove($list);
        }

        if (! $result->successful()) {
            throw new DatabaseBackupException($this->failure('pg_restore', $result->exitCode, $result->errorOutput));
        }
    }

    public function restoreAllowed(): bool
    {
        return (bool) config('sciem.respaldo.restaurar_permitido', false);
    }

    /** @throws DatabaseBackupException */
    public function assertRestoreAllowed(): void
    {
        if (! $this->restoreAllowed()) {
            throw new DatabaseBackupException(
                'La restauración está deshabilitada en esta máquina. Ponga SCIEM_RESTAURAR_PERMITIDO=true en el '
                . '.env solo mientras restaura y vuelva a ponerlo en false al terminar (con config:cache, ejecute '
                . 'php artisan config:cache de nuevo después de cada cambio).'
            );
        }
    }

    /**
     * Host y nombre de la base de la conexión por defecto (ya resueltos, también si vienen de DATABASE_URL).
     *
     * @return array{host: string, database: string}
     */
    public function target(): array
    {
        $config = DB::connection()->getConfig();
        $host = $config['host'] ?? '';

        return [
            'host' => trim((string) (is_array($host) ? reset($host) : $host)),
            'database' => (string) ($config['database'] ?? ''),
        ];
    }

    /** La base compartida de desarrollo vive en *.supabase.com o *.supabase.co. */
    public function isSupabaseHost(string $host): bool
    {
        return preg_match('/(^|\.)supabase\.(com|co)$/i', rtrim(trim($host), '.')) === 1;
    }

    /**
     * Respaldos guardados, del más reciente al más antiguo.
     *
     * @return array<int, string> rutas completas
     */
    public function backups(): array
    {
        $directory = $this->directory();

        if (! is_dir($directory)) {
            return [];
        }

        $files = [];

        foreach (scandir($directory) ?: [] as $name) {
            $isBackup = strpos($name, self::PREFIX) === 0
                && substr($name, -strlen(self::EXTENSION)) === self::EXTENSION;

            if ($isBackup && is_file($directory . DIRECTORY_SEPARATOR . $name)) {
                $files[] = $directory . DIRECTORY_SEPARATOR . $name;
            }
        }

        rsort($files, SORT_STRING);

        return $files;
    }

    /**
     * Borra los respaldos que exceden la retención (nunca menos de uno), salvo los protegidos.
     *
     * @param  array<int, string>  $protected  Rutas que no se borran aunque excedan la retención.
     * @return array<int, string> rutas borradas
     */
    public function prune(array $protected = []): array
    {
        $keep = max(1, (int) config('sciem.respaldo.retencion', 7));
        $safe = array_map([$this, 'normalize'], $protected);
        $removed = [];

        foreach (array_slice($this->backups(), $keep) as $file) {
            if (in_array($this->normalize($file), $safe, true)) {
                continue;
            }

            $this->remove($file);
            $removed[] = $file;
        }

        return $removed;
    }

    public function directory(): string
    {
        $configured = trim((string) config('sciem.respaldo.directorio', ''));
        $directory = $configured === '' ? storage_path('app/respaldos') : $configured;

        return rtrim($directory, '/\\') ?: $directory;
    }

    /**
     * Un nombre de archivo suelto se busca en el directorio de respaldos; una ruta se usa tal cual.
     *
     * @throws DatabaseBackupException
     */
    public function locate(string $file): string
    {
        foreach ([$file, $this->directory() . DIRECTORY_SEPARATOR . $file] as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        throw new DatabaseBackupException('No existe el respaldo «' . $file . '».');
    }

    /**
     * El índice del respaldo sin la entrada del esquema public (ni su comentario), en un archivo temporal
     * para --use-list. Así --clean borra y recrea tablas, tipos y funciones, pero no el esquema.
     */
    private function restoreList(string $path): string
    {
        $result = $this->process->run([$this->pgRestore(), '--list', $path], [], $this->timeout());

        if (! $result->successful()) {
            throw new DatabaseBackupException($this->failure('pg_restore', $result->exitCode, $result->errorOutput));
        }

        $schemaEntry = '/^\d+;\s+\d+\s+\d+\s+(SCHEMA - public|COMMENT - SCHEMA public)\b/';
        $lines = array_filter(
            preg_split('/\R/', $result->output) ?: [],
            fn (string $line) => preg_match($schemaEntry, $line) !== 1
        );

        $list = tempnam(sys_get_temp_dir(), 'sciem_restaurar_');

        if ($list === false || file_put_contents($list, implode("\n", $lines) . "\n") === false) {
            throw new DatabaseBackupException('No se pudo preparar la lista de restauración.');
        }

        return $list;
    }

    private function pgDump(): string
    {
        return (string) config('sciem.respaldo.pg_dump', 'pg_dump');
    }

    private function pgRestore(): string
    {
        return (string) config('sciem.respaldo.pg_restore', 'pg_restore');
    }

    /** sciem_AAAAMMDD_HHMMSS.dump con la hora de La Paz; si ya existe (dos en el mismo segundo), con sufijo. */
    private function uniquePath(string $directory): string
    {
        $stamp = Carbon::now((string) config('sciem.zona_horaria', 'America/La_Paz'))->format('Ymd_His');
        $base = $directory . DIRECTORY_SEPARATOR . self::PREFIX . $stamp;
        $path = $base . self::EXTENSION;

        for ($suffix = 2; file_exists($path); $suffix++) {
            $path = $base . '_' . $suffix . self::EXTENSION;
        }

        return $path;
    }

    /** En Windows un antivirus puede tener abierto un instante el archivo recién cerrado: se reintenta. */
    private function moveInto(string $partial, string $file): bool
    {
        for ($attempt = 1; $attempt <= self::RENAME_ATTEMPTS; $attempt++) {
            if (@rename($partial, $file)) {
                return true;
            }

            if ($attempt < self::RENAME_ATTEMPTS) {
                usleep(200000);
            }
        }

        return false;
    }

    private function ensureDirectory(string $directory): void
    {
        $this->assertOutsideRepository($directory);

        if (! is_dir($directory) && ! @mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new DatabaseBackupException('No se pudo crear el directorio de respaldos ' . $directory . '.');
        }
    }

    /**
     * Los respaldos no pueden quedar dentro de una copia de git: un `git add .` los subiría. Se busca un
     * `.git` en el directorio o en sus ancestros; solo se exceptúa storage/app, que ya está ignorado.
     */
    private function assertOutsideRepository(string $directory): void
    {
        $existing = $directory;

        while (! is_dir($existing) && dirname($existing) !== $existing) {
            $existing = dirname($existing);
        }

        $resolved = realpath($existing);

        if ($resolved === false) {
            return;
        }

        $ignored = realpath(storage_path('app'));

        if ($ignored !== false && $this->isInside($resolved, $ignored)) {
            return;
        }

        $current = $resolved;

        while (true) {
            if (file_exists($current . DIRECTORY_SEPARATOR . '.git')) {
                throw new DatabaseBackupException(
                    'El directorio de respaldos ' . $directory . ' queda dentro de un repositorio de git. '
                    . 'Use un directorio fuera del repositorio (SCIEM_RESPALDO_DIRECTORIO) o storage/app/respaldos.'
                );
            }

            $parent = dirname($current);

            if ($parent === $current) {
                return;
            }

            $current = $parent;
        }
    }

    private function isInside(string $path, string $parent): bool
    {
        $path = $this->normalize($path);
        $parent = rtrim($this->normalize($parent), '/') . '/';

        return strpos($path . '/', $parent) === 0;
    }

    /** Separadores unificados y, en Windows, sin distinguir mayúsculas: así se comparan rutas. */
    private function normalize(string $path): string
    {
        $path = str_replace('\\', '/', (string) (realpath($path) ?: $path));

        return DIRECTORY_SEPARATOR === '\\' ? strtolower($path) : $path;
    }

    /**
     * Variables libpq de la conexión por defecto (ya resuelta, también si viene de DATABASE_URL).
     *
     * @return array<string, string>
     */
    private function connectionEnv(): array
    {
        $config = DB::connection()->getConfig();

        if (($config['driver'] ?? null) !== 'pgsql') {
            throw new DatabaseBackupException('El respaldo solo funciona con una conexión PostgreSQL.');
        }

        $target = $this->target();

        return array_filter([
            'PGHOST' => $target['host'],
            'PGPORT' => (string) ($config['port'] ?? ''),
            'PGDATABASE' => $target['database'],
            'PGUSER' => (string) ($config['username'] ?? ''),
            'PGPASSWORD' => (string) ($config['password'] ?? ''),
            'PGSSLMODE' => (string) ($config['sslmode'] ?? ''),
        ], fn (string $value) => $value !== '');
    }

    private function database(): string
    {
        return $this->target()['database'];
    }

    private function schema(): string
    {
        $schema = DB::connection()->getConfig('schema') ?: 'public';

        return is_array($schema) ? (string) reset($schema) : (string) $schema;
    }

    private function timeout(): int
    {
        return max(60, (int) config('sciem.respaldo.timeout', 1800));
    }

    /** El mensaje de error del programa, sin la contraseña por si alguna vez la repitiera. */
    private function failure(string $program, int $exitCode, string $errorOutput): string
    {
        $password = (string) DB::connection()->getConfig('password');
        $detail = $password === '' ? $errorOutput : str_replace($password, '********', $errorOutput);
        $hint = $exitCode === ExternalProcess::NOT_FOUND ? ' Revise SCIEM_PG_DUMP y SCIEM_PG_RESTORE en el .env.' : '';

        return $program . ' terminó con el código ' . $exitCode . ($detail === '' ? '.' : ': ' . $detail) . $hint;
    }

    private function remove(string $file): void
    {
        if (is_file($file)) {
            @unlink($file);
        }
    }
}
