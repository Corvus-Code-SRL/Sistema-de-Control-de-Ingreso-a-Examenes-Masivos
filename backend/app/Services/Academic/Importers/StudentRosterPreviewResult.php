<?php

namespace App\Services\Academic\Importers;

final class StudentRosterPreviewResult
{
    public const INCONSISTENT = 'inconsistent';

    private string $token;

    private int $totalRows;

    private int $validRows;

    private int $inconsistentRows;

    /**
     * @var array<int, array{
     *     row_number: int,
     *     sis_code: ?string,
     *     last_names: ?string,
     *     first_names: ?string,
     *     career_text: ?string,
     *     career: ?array{id_carrera: int, nombre: string},
     *     status: string,
     *     errors: array<int, string>
     * }>
     */
    private array $rows;

    /**
     * Carreras que atiende el grupo (HU-021 multicarrera).
     *
     * @var array<int, array{id_carrera: int, codigo: string, nombre: string, principal: bool}>
     */
    private array $groupCareers;

    /**
     * @param array<int, array{
     *     row_number: int,
     *     sis_code: ?string,
     *     last_names: ?string,
     *     first_names: ?string,
     *     career_text: ?string,
     *     career: ?array{id_carrera: int, nombre: string},
     *     status: string,
     *     errors: array<int, string>
     * }> $rows
     * @param array<int, array{id_carrera: int, codigo: string, nombre: string, principal: bool}> $groupCareers
     */
    public function __construct(
        string $token,
        int $totalRows,
        int $validRows,
        int $inconsistentRows,
        array $rows,
        array $groupCareers = []
    ) {
        $this->token = $token;
        $this->totalRows = $totalRows;
        $this->validRows = $validRows;
        $this->inconsistentRows = $inconsistentRows;
        $this->rows = $rows;
        $this->groupCareers = $groupCareers;
    }

    /**
     * @return array<int, array{id_carrera: int, codigo: string, nombre: string, principal: bool}>
     */
    public function groupCareers(): array
    {
        return $this->groupCareers;
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

    /**
     * @return array<int, array{
     *     row_number: int,
     *     sis_code: ?string,
     *     last_names: ?string,
     *     first_names: ?string,
     *     career_text: ?string,
     *     career: ?array{id_carrera: int, nombre: string},
     *     status: string,
     *     errors: array<int, string>
     * }>
     */
    public function rows(): array
    {
        return $this->rows;
    }
}
