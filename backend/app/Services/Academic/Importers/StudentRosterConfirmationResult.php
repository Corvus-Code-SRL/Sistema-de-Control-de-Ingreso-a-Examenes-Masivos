<?php

namespace App\Services\Academic\Importers;

final class StudentRosterConfirmationResult
{
    private int $totalRows;

    private int $inconsistentRows;

    private int $createdStudents;

    private int $enrolledStudents;

    private int $alreadyEnrolled;

    public function __construct(
        int $totalRows,
        int $inconsistentRows,
        int $createdStudents,
        int $enrolledStudents,
        int $alreadyEnrolled
    ) {
        $this->totalRows = $totalRows;
        $this->inconsistentRows = $inconsistentRows;
        $this->createdStudents = $createdStudents;
        $this->enrolledStudents = $enrolledStudents;
        $this->alreadyEnrolled = $alreadyEnrolled;
    }

    public function totalRows(): int
    {
        return $this->totalRows;
    }

    public function inconsistentRows(): int
    {
        return $this->inconsistentRows;
    }

    public function createdStudents(): int
    {
        return $this->createdStudents;
    }

    public function enrolledStudents(): int
    {
        return $this->enrolledStudents;
    }

    public function alreadyEnrolled(): int
    {
        return $this->alreadyEnrolled;
    }
}