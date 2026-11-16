<?php

namespace App\Services\Exams\Importers;

/**
 * Resultado de confirmar la importación de una lista de habilitados
 * (HU-028).
 *
 * IMPORTANTE: este resultado NO se aplica. El Service solo valida el
 * token, re-analiza el archivo, y devuelve los cambios listos para que
 * el cliente los muestre como "cambios pendientes". Aplicarlos es
 * responsabilidad de PUT /habilitados (SaveEligibleListChangesRequest).
 *
 * `cambios_pendientes[]` trae, por cada fila MATCHED del archivo, el
 * estado propuesto. Las filas MATCHED del archivo se proponen como
 * HABILITADO (están en el archivo). Las filas de la nómina que NO están
 * en el archivo se proponen como NO_HABILITADO, con el motivo y el
 * detalle que el docente eligió en el panel de importación.
 *
 * Las filas INCONSISTENT y NOT_FOUND_IN_EXAM no generan cambios.
 */
final class EligibleListImportResult
{
    private int $totalRows;

    private int $inconsistentRows;

    private int $noEncontrados;

    /**
     * @var array<int, array{
     *     id_estudiante: int,
     *     codigo_sis: string,
     *     estado_habilitacion: string,
     *     motivo: ?string,
     *     detalle: ?string
     * }>
     */
    private array $cambiosPendientes;

    /**
     * @param array<int, array{
     *     id_estudiante: int,
     *     codigo_sis: string,
     *     estado_habilitacion: string,
     *     motivo: ?string,
     *     detalle: ?string
     * }> $cambiosPendientes
     */
    public function __construct(
        int $totalRows,
        int $inconsistentRows,
        int $noEncontrados,
        array $cambiosPendientes
    ) {
        $this->totalRows = $totalRows;
        $this->inconsistentRows = $inconsistentRows;
        $this->noEncontrados = $noEncontrados;
        $this->cambiosPendientes = $cambiosPendientes;
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

    /**
     * @return array<int, array{
     *     id_estudiante: int,
     *     codigo_sis: string,
     *     estado_habilitacion: string,
     *     motivo: ?string,
     *     detalle: ?string
     * }>
     */
    public function cambiosPendientes(): array
    {
        return $this->cambiosPendientes;
    }
}
