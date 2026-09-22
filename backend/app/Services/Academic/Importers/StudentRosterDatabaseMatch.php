<?php

namespace App\Services\Academic\Importers;

final class StudentRosterDatabaseMatch
{
    public const NEW_STUDENT = 'new_student';

    public const EXISTING_STUDENT = 'existing_student';

    public const ALREADY_ENROLLED = 'already_enrolled';

    public const INACTIVE_ENROLLMENT = 'inactive_enrollment';

    private StudentRosterRowAnalysis $rowAnalysis;

    private ?int $studentId;

    private string $status;

    public function __construct(
        StudentRosterRowAnalysis $rowAnalysis,
        ?int $studentId,
        string $status
    ) {
        $this->rowAnalysis = $rowAnalysis;
        $this->studentId = $studentId;
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

    public function status(): string
    {
        return $this->status;
    }
}