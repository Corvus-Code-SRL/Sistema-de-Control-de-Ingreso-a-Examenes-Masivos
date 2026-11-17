<?php

namespace App\Services\Academic;

/**
 * Datos validados para registrar un estudiante excepcional en un examen (HU-023).
 */
final class ExceptionalStudentData
{
    public int $examId;

    public string $teacherId;

    public int $studentId;

    public string $reason;

    public int $groupId;

    public int $careerId;

    public int $roomId;

    public function __construct(
        int $examId,
        string $teacherId,
        int $studentId,
        string $reason,
        int $groupId,
        int $careerId,
        int $roomId
    ) {
        $this->examId = $examId;
        $this->teacherId = $teacherId;
        $this->studentId = $studentId;
        $this->reason = trim($reason);
        $this->groupId = $groupId;
        $this->careerId = $careerId;
        $this->roomId = $roomId;
    }
}
