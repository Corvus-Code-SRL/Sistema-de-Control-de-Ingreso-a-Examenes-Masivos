<?php

namespace App\Support\Process;

/** Resultado de un programa externo: su código de salida, su salida y lo que escribió como error. */
class ProcessResult
{
    public int $exitCode;

    public string $errorOutput;

    public string $output;

    public function __construct(int $exitCode, string $errorOutput, string $output = '')
    {
        $this->exitCode = $exitCode;
        $this->errorOutput = $errorOutput;
        $this->output = $output;
    }

    public function successful(): bool
    {
        return $this->exitCode === 0;
    }
}
