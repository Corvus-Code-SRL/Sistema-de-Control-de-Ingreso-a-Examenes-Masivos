<?php

namespace App\Support\Process;

use Symfony\Component\Process\Exception\ExceptionInterface;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Ejecuta un programa externo (pg_dump, pg_restore) sin pasar por una shell: cada argumento viaja tal cual,
 * así una ruta con espacios (C:\Program Files\PostgreSQL\15\bin\pg_dump.exe) o un nombre de base raro no se
 * interpretan. Las credenciales van por el entorno del proceso, nunca en los argumentos (que se ven en la
 * lista de procesos del sistema).
 *
 * Es una clase aparte para que las pruebas la reemplacen y no ejecuten nada.
 */
class ExternalProcess
{
    /** Código que se devuelve cuando el programa no existe o no se pudo lanzar (el de «command not found»). */
    public const NOT_FOUND = 127;

    /** Código que se devuelve cuando se supera el tiempo máximo (el de timeout(1)). */
    public const TIMED_OUT = 124;

    /**
     * @param  array<int, string>  $command  Programa y argumentos.
     * @param  array<string, string>  $env  Variables que se agregan al entorno del proceso.
     */
    public function run(array $command, array $env, int $timeoutSeconds): ProcessResult
    {
        // Se revisa antes de lanzarlo: en Windows Symfony pasa por cmd.exe y un programa inexistente devuelve 1
        // («no se reconoce como un comando…»), no 127 como en Linux.
        if (! $this->exists((string) ($command[0] ?? ''))) {
            return new ProcessResult(self::NOT_FOUND, $this->notFound($command));
        }

        $process = new Process($command, null, $env, null, $timeoutSeconds);

        try {
            $process->run();
        } catch (ProcessTimedOutException $exception) {
            return new ProcessResult(
                self::TIMED_OUT,
                'Se superó el tiempo máximo de ' . $timeoutSeconds . ' segundos.'
            );
        } catch (ExceptionInterface $exception) {
            // En Windows un ejecutable inexistente falla al lanzarse en lugar de devolver un código.
            return new ProcessResult(self::NOT_FOUND, $this->notFound($command));
        }

        if ($process->getExitCode() === self::NOT_FOUND) {
            return new ProcessResult(self::NOT_FOUND, $this->notFound($command));
        }

        return new ProcessResult(
            (int) $process->getExitCode(),
            trim($process->getErrorOutput()),
            $process->getOutput()
        );
    }

    private function exists(string $program): bool
    {
        return self::programExists($program, DIRECTORY_SEPARATOR === '\\', (string) getenv('PATHEXT'));
    }

    /**
     * Una ruta (con / o \) debe ser un archivo; un nombre suelto (pg_dump) debe estar en el PATH.
     *
     * En Windows, una ruta sin extensión (C:\...\bin\pg_dump) también vale si existe con alguna extensión de
     * PATHEXT (pg_dump.exe), como la resuelve cmd.exe. Es pública y estática para probar el caso de Windows
     * desde cualquier sistema.
     */
    public static function programExists(string $program, bool $windows, string $pathExt = ''): bool
    {
        if ($program === '') {
            return false;
        }

        if (! str_contains($program, '/') && ! str_contains($program, '\\')) {
            return (new ExecutableFinder())->find($program) !== null;
        }

        if (is_file($program)) {
            return true;
        }

        if (! $windows || pathinfo($program, PATHINFO_EXTENSION) !== '') {
            return false;
        }

        $extensions = $pathExt !== '' ? $pathExt : '.COM;.EXE;.BAT;.CMD';
        // PATHEXT viene en mayúsculas (.EXE); Windows no distingue mayúsculas, pero las pruebas en Linux sí.
        foreach (explode(';', $extensions) as $extension) {
            if ($extension !== '' && (is_file($program . $extension) || is_file($program . strtolower($extension)))) {
                return true;
            }
        }

        return false;
    }

    /** @param  array<int, string>  $command */
    private function notFound(array $command): string
    {
        return 'No se pudo ejecutar «' . ($command[0] ?? '') . '»: el programa no existe o no se puede ejecutar.';
    }
}
