<?php

namespace App\Services\Academic\Importers;

final class StudentRosterRow
{
    private int $rowNumber;

    private ?string $sisCode;

    private ?string $lastNames;

    private ?string $firstNames;

    public function __construct(
        int $rowNumber,
        ?string $sisCode,
        ?string $lastNames,
        ?string $firstNames
    ) {
        $this->rowNumber = $rowNumber;
        $this->sisCode = $sisCode;
        $this->lastNames = $lastNames;
        $this->firstNames = $firstNames;
    }

    public function rowNumber(): int
    {
        return $this->rowNumber;
    }

    public function sisCode(): ?string
    {
        return $this->sisCode;
    }

    public function lastNames(): ?string
    {
        return $this->lastNames;
    }

    public function firstNames(): ?string
    {
        return $this->firstNames;
    }
}