<?php

namespace App\Services\Academic\Importers;

final class StudentRosterPreview
{
    private ?int $groupId;   // ← nullable

    private string $teacherId;

    /**
     * @var array<int, StudentRosterRow>
     */
    private array $rows;

    /**
     * @param array<int, StudentRosterRow> $rows
     */
    public function __construct(
        ?int $groupId,
        string $teacherId,
        array $rows
    ) {
        $this->groupId = $groupId;
        $this->teacherId = $teacherId;
        $this->rows = $rows;
    }

    public function groupId(): ?int   // ← nullable
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