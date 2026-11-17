<?php

namespace App\Services\Exams\Importers;

/**
 * Resultado de confirmar la carga de una lista de habilitados (HU-028).
 *
 * La carga ya se aplicó cuando se construye este resultado: los estudiantes
 * del archivo quedaron habilitados y los de la nómina que no figuran en él
 * quedaron «No habilitado» con el motivo fijo. `noHabilitados` los lista con
 * su código SIS y su nombre para mostrárselos al docente.
 */
final class EligibleListImportResult
{
    private int $totalRows;

    private int $inconsistentRows;

    private int $noEncontrados;

    private int $habilitados;

    /** @var array<int, array{id_estudiante: int, codigo_sis: string, nombre_completo: string}> */
    private array $noHabilitados;

    /**
     * @param array<int, array{id_estudiante: int, codigo_sis: string, nombre_completo: string}> $noHabilitados
     */
    public function __construct(
        int $totalRows,
        int $inconsistentRows,
        int $noEncontrados,
        int $habilitados,
        array $noHabilitados
    ) {
        $this->totalRows = $totalRows;
        $this->inconsistentRows = $inconsistentRows;
        $this->noEncontrados = $noEncontrados;
        $this->habilitados = $habilitados;
        $this->noHabilitados = $noHabilitados;
    }

    public function totalRows(): int
    {
        return $this->totalRows;
    }

    public function inconsistentRows(): int
    {
        return $this->inconsistentRows;
    }

    public function noEncontrados(): int
    {
        return $this->noEncontrados;
    }

    public function habilitados(): int
    {
        return $this->habilitados;
    }

    /** @return array<int, array{id_estudiante: int, codigo_sis: string, nombre_completo: string}> */
    public function noHabilitados(): array
    {
        return $this->noHabilitados;
    }
}
