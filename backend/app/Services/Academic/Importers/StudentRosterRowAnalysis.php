<?php

namespace App\Services\Academic\Importers;

final class StudentRosterRowAnalysis
{
    private StudentRosterRow $row;

    /**
     * @var array<int, string>
     */
    private array $errors;

    /**
     * @param array<int, string> $errors
     */
    public function __construct(
        StudentRosterRow $row,
        array $errors
    ) {
        $this->row = $row;
        $this->errors = $errors;
    }

    public function row(): StudentRosterRow
    {
        return $this->row;
    }

    /**
     * @return array<int, string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    public function isValid(): bool
    {
        return $this->errors === [];
    }
}