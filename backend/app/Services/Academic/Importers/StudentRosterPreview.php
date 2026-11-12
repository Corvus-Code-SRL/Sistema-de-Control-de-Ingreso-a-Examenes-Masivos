<?php

namespace App\Services\Academic\Importers;

final class StudentRosterPreview
{
    private int $groupId;

    private string $teacherId;

    /**
     * @var array<int, StudentRosterRow>
     */
    private array $rows;

    /** Carrera elegida para las filas sin columna «carrera» (HU-021 multicarrera). */
    private ?int $defaultCareerId;

    /**
     * @param array<int, StudentRosterRow> $rows
     */
    public function __construct(
        int $groupId,
        string $teacherId,
        array $rows,
        ?int $defaultCareerId = null
    ) {
        $this->groupId = $groupId;
        $this->teacherId = $teacherId;
        $this->rows = $rows;
        $this->defaultCareerId = $defaultCareerId;
    }

    public function defaultCareerId(): ?int
    {
        return $this->defaultCareerId;
    }

    public function groupId(): int
    {
        return $this->groupId;
    }

    public function teacherId(): string
    {
        return $this->teacherId;
    }

    /**
     * @return array<int, StudentRosterRow>
     */
    public function rows(): array
    {
        return $this->rows;
    }
}
