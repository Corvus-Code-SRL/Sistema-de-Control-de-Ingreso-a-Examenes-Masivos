<?php

namespace App\Support\Process;

use Symfony\Component\Process\Exception\ExceptionInterface;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
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

    /** @param  array<int, string>  $command */
    private function notFound(array $command): string
    {
        return 'No se pudo ejecutar «' . ($command[0] ?? '') . '»: el programa no existe o no se puede ejecutar.';
    }
}
