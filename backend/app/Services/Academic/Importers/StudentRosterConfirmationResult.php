<?php

namespace App\Services\Academic\Importers;

final class StudentRosterConfirmationResult
{
    private int $totalRows;

    private int $inconsistentRows;

    private int $createdStudents;

    private int $enrolledStudents;

    private int $alreadyEnrolled;

    private int $inactiveEnrollments;

    public function __construct(
        int $totalRows,
        int $inconsistentRows,
        int $createdStudents,
        int $enrolledStudents,
        int $alreadyEnrolled,
        int $inactiveEnrollments
    ) {
        $this->totalRows = $totalRows;
        $this->inconsistentRows = $inconsistentRows;
        $this->createdStudents = $createdStudents;
        $this->enrolledStudents = $enrolledStudents;
        $this->alreadyEnrolled = $alreadyEnrolled;
        $this->inactiveEnrollments = $inactiveEnrollments;
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

    public function inactiveEnrollments(): int
    {
        return $this->inactiveEnrollments;
    }
}