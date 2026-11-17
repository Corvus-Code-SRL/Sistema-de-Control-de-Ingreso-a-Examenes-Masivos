<?php

namespace App\Services\Exams\Distribution;

use App\Exceptions\Exams\ExamOwnershipException;
use App\Exceptions\Exams\ExamStateException;
use App\Models\Exam;
use App\Services\Exams\ExamCollaboratorService;
use App\Services\Exams\ExamPreparationWindow;
use App\Services\Security\UserRoleService;
use App\Support\Exams\DistributionMessages;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/** Permisos propios de HU-031: el creador escribe y los colaboradores aceptados consultan. */
class RoomDistributionAccess
{
    public function __construct(
        private ExamCollaboratorService $collaborators,
        private ExamPreparationWindow $window,
        private UserRoleService $roles
    ) {
    }

    public function forRead(int $examId, string $teacherId): Exam
    {
        $this->assertTeacher($teacherId);
        $exam = Exam::query()->findOrFail($examId);

        if (! $this->collaborators->isTeacherOf($exam, $teacherId)) {
            throw new ExamOwnershipException();
        }

        return $exam;
    }

    /** Debe ejecutarse dentro de la transacción; serializa también cambios de rol y apertura. */
    public function lockForWrite(int $examId, string $teacherId): Exam
    {
        $exam = Exam::query()->whereKey($examId)->lockForUpdate()->firstOrFail();
        // HU-024 bloquea examen → cuenta. SHARE impide cambios de rol y admite las FK de bitácora.
        DB::table('usuario')->where('id_usuario', $teacherId)->sharedLock()->first();
        $this->assertTeacher($teacherId);
        $this->assertWritable($exam, $teacherId);

        return $exam;
    }

    /** Protege la ventana mientras se calcula un borrador, sin bloquear sus fuentes para escritura. */
    public function lockForProposal(int $examId, string $teacherId): Exam
    {
        $this->assertTeacher($teacherId);
        $exam = Exam::query()->whereKey($examId)->sharedLock()->firstOrFail();
        $this->assertWritable($exam, $teacherId);

        return $exam;
    }

    public function assertTeacher(string $teacherId): void
    {
        if (! $this->roles->isTeacher($teacherId)) {
            throw new AuthorizationException(DistributionMessages::NO_PERMISSION);
        }
    }

    public function readOnlyReason(Exam $exam, string $teacherId): ?string
    {
        if ((string) $exam->id_usuario_docente !== $teacherId) {
            return DistributionMessages::COLLABORATOR_READ_ONLY;
        }

        return $this->window->isOpen($exam) ? null : ExamPreparationWindow::CLOSED_MESSAGE;
    }

    private function assertWritable(Exam $exam, string $teacherId): void
    {
        if ((string) $exam->id_usuario_docente !== $teacherId) {
            throw new ExamOwnershipException();
        }

        if (! $this->window->isOpen($exam)) {
            throw new ExamStateException(ExamPreparationWindow::CLOSED_MESSAGE);
        }
    }
}
