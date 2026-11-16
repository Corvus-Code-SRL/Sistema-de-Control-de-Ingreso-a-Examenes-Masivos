<?php

namespace App\Services\Exams\Importers;

use App\Services\Academic\Importers\StudentRosterRowAnalysis;

/**
 * Resultado de cruzar una fila del archivo de lista de habilitados contra
 * examen_estudiante del examen (HU-028).
 */
final class EligibleListDatabaseMatch
{
    public const MATCHED = 'matched';

    public const NOT_FOUND_IN_EXAM = 'not_found_in_exam';

    private StudentRosterRowAnalysis $rowAnalysis;

    private ?int $studentId;

    private ?string $studentName;

    private ?int $groupId;

    private ?string $groupName;

    private string $status;

    public function __construct(
        StudentRosterRowAnalysis $rowAnalysis,
        ?int $studentId,
        ?string $studentName,
        ?int $groupId,
        ?string $groupName,
        string $status
    ) {
        $this->rowAnalysis = $rowAnalysis;
        $this->studentId = $studentId;
        $this->studentName = $studentName;
        $this->groupId = $groupId;
        $this->groupName = $groupName;
        $this->status = $status;
    }

    public function rowAnalysis(): StudentRosterRowAnalysis
    {
        return $this->rowAnalysis;
    }

    public function studentId(): ?int
    {
        return $this->studentId;
    }

    public function studentName(): ?string
    {
        return $this->studentName;
    }

    public function groupId(): ?int
    {
        return $this->groupId;
    }

    public function groupName(): ?string
    {
        return $this->groupName;
    }

    public function status(): string
    {
        return $this->status;
    }
}
