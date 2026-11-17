<?php

namespace App\Services\Exams\Importers;

/**
 * Resultado del preview de la lista de habilitados (HU-028).
 *
 * `status` por fila:
 * - self::INCONSISTENT (fila inválida o duplicada en el archivo)
 * - self::MATCHED (fila válida y con fila en examen_estudiante del examen)
 * - self::NOT_FOUND_IN_EXAM (fila válida pero el cod_sis no tiene fila
 *   en examen_estudiante del examen)
 *
 * El frontend compara con 'matched' / 'not_found_in_exam' /
 * 'inconsistent' literales.
 */
final class EligibleListPreviewResult
{
    public const MATCHED = 'matched';

    public const NOT_FOUND_IN_EXAM = 'not_found_in_exam';

    public const INCONSISTENT = 'inconsistent';

    private string $token;

    private int $totalRows;

    private int $validRows;

    private int $inconsistentRows;

    private int $faltantes;

    /**
     * @var array<int, array{
     *     row_number: int,
     *     sis_code: ?string,
     *     last_names: ?string,
     *     first_names: ?string,
     *     status: string,
     *     errors: array<int, string>,
     *     student_name: ?string,
     *     group_name: ?string
     * }>
     */
    private array $rows;

    /**
     * @param array<int, array{
     *     row_number: int,
     *     sis_code: ?string,
     *     last_names: ?string,
     *     first_names: ?string,
     *     status: string,
     *     errors: array<int, string>,
     *     student_name: ?string,
     *     group_name: ?string
     * }> $rows
     */
    public function __construct(
        string $token,
        int $totalRows,
        int $validRows,
        int $inconsistentRows,
        int $faltantes,
        array $rows
    ) {
        $this->token = $token;
        $this->totalRows = $totalRows;
        $this->validRows = $validRows;
        $this->inconsistentRows = $inconsistentRows;
        $this->faltantes = $faltantes;
        $this->rows = $rows;
    }

    public function token(): string
    {
        return $this->token;
    }

    public function totalRows(): int
    {
        return $this->totalRows;
    }

    public function validRows(): int
    {
        return $this->validRows;
    }

    public function inconsistentRows(): int
    {
        return $this->inconsistentRows;
    }

    public function faltantes(): int
    {
        return $this->faltantes;
    }

    /**
     * @return array<int, array{
     *     row_number: int,
     *     sis_code: ?string,
     *     last_names: ?string,
     *     first_names: ?string,
     *     status: string,
     *     errors: array<int, string>,
     *     student_name: ?string,
     *     group_name: ?string
     * }>
     */
    public function rows(): array
    {
        return $this->rows;
    }
}
