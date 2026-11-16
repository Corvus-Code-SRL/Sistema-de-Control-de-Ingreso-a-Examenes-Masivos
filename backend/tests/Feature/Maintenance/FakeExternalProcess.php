<?php

namespace Tests\Feature\Maintenance;

use App\Support\Process\ExternalProcess;
use App\Support\Process\ProcessResult;

/**
 * Reemplaza pg_dump / pg_restore en las pruebas: registra cada llamada y simula el resultado.
 * pg_dump escribe el archivo de --file= (salvo que se pida fallar); pg_restore --list devuelve un índice.
 */
class FakeExternalProcess extends ExternalProcess
{
    /** @var array<int, array{command: array<int, string>, env: array<string, string>, list: string|null}> */
    public array $calls = [];

    public int $dumpExitCode = 0;

    public string $dumpError = '';

    public int $restoreExitCode = 0;

    public const LIST = "; Archive created at 2026-11-11\n"
        . "6; 2615 92849 SCHEMA - public postgres\n"
        . "7; 0 0 COMMENT - SCHEMA public postgres\n"
        . "223; 1259 92976 TABLE public accion postgres\n"
        . "4100; 0 92976 TABLE DATA public accion postgres\n";

    public function run(array $command, array $env, int $timeoutSeconds): ProcessResult
    {
        $useList = null;

        foreach ($command as $argument) {
            if (strpos($argument, '--use-list=') === 0) {
                $useList = (string) file_get_contents(substr($argument, strlen('--use-list=')));
            }
        }

        $this->calls[] = ['command' => $command, 'env' => $env, 'list' => $useList];

        if (in_array('--list', $command, true)) {
            return new ProcessResult(0, '', self::LIST);
        }

        if ($this->program($command[0]) === 'pg_restore') {
            return new ProcessResult($this->restoreExitCode, $this->restoreExitCode === 0 ? '' : 'pg_restore: error');
        }

        foreach ($command as $argument) {
            if (strpos($argument, '--file=') === 0) {
                file_put_contents(substr($argument, strlen('--file=')), 'PGDMP respaldo simulado');
            }
        }

        return new ProcessResult($this->dumpExitCode, $this->dumpError);
    }

    /**
     * Las llamadas a un programa por su nombre, sin carpeta ni «.exe»: así también se reconoce una ruta de
     * Windows (C:\Program Files\PostgreSQL\15\bin\pg_dump.exe) aunque la prueba corra en Linux.
     *
     * @return array<int, array{command: array<int, string>, env: array<string, string>, list: string|null}>
     */
    public function callsTo(string $program): array
    {
        return array_values(array_filter(
            $this->calls,
            fn (array $call) => $this->program($call['command'][0]) === $program
        ));
    }

    private function program(string $path): string
    {
        $parts = preg_split('~[\\\\/]~', $path) ?: [$path];

        return (string) preg_replace('/\.exe$/i', '', (string) end($parts));
    }
}
