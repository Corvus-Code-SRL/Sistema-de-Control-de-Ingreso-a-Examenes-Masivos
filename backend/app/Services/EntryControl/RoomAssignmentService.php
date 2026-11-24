<?php

namespace App\Services\EntryControl;

use App\Exceptions\EntryControl\EntryOpeningException;
use App\Models\Exam;
use App\Services\Exams\ExamInvitationExpiryService;
use App\Services\Exams\ExamRosterService;
use App\Services\Security\AuditLogService;
use DomainException;
use Illuminate\Support\Facades\DB;

class RoomAssignmentService
{
    private EntryControlSnapshotService $snapshots;
    private AuditLogService $auditLog;
    private ExamRosterService $roster;

    public function __construct(
        EntryControlSnapshotService $snapshots,
        AuditLogService $auditLog,
        ExamRosterService $roster
    ) {
        $this->snapshots = $snapshots;
        $this->auditLog = $auditLog;
        $this->roster = $roster;
    }

    /**
     * Valida nómina y ambientes antes de abrir; la capacidad ya no impide abrir: solo avisa al registrar.
     */
    public function prepare(int $examId): void
    {
        DB::transaction(function () use ($examId): void {
            $exam = Exam::query()->whereKey($examId)->lockForUpdate()->firstOrFail();

            if (! in_array($exam->estado, [Exam::PROGRAMADO, Exam::EN_CURSO], true)
                || $exam->ingreso_ordinario_cerrado_en !== null) {
                throw new DomainException('El examen no admite preparación de ambientes.');
            }

            $studentCount = $this->roster->expectedTotals([$examId])[$examId] ?? 0;

            if ($studentCount === 0) {
                throw new EntryOpeningException(
                    EntryOpeningException::MISSING_ROSTER,
                    'El examen no tiene estudiantes en su nómina.'
                );
            }

            if (! DB::table('examen_ambiente')->where('id_examen', $examId)->exists()) {
                throw new EntryOpeningException(
                    EntryOpeningException::MISSING_ROOMS,
                    'El examen no tiene ambientes asignados.'
                );
            }

            $token = $this->snapshots->stage($exam);

            if ($exam->ingreso_abierto_en === null) {
                $exam->ingreso_abierto_en = now();
                $exam->save();
                app(ExamInvitationExpiryService::class)->voidPendingForExam((int) $exam->id_examen);
                $this->auditLog->registrar(
                    'MODIFICAR',
                    'examen',
                    ['id_examen' => $exam->id_examen],
                    ['id_examen' => $exam->id_examen, 'evento' => 'APERTURA_CONTROL'],
                    (string) $exam->id_usuario_docente
                );
            }

            $this->snapshots->activate($examId, $token);
        });
    }

    /**
     * Ambiente planificado del estudiante según la distribución guardada; null si no tiene.
     */
    public function assignedRoomFor(int $examId, int $studentId): ?object
    {
        return $this->snapshots->assignedRoomFor($examId, $studentId);
    }

    public function hasPreparedSnapshot(int $examId): bool
    {
        return $this->snapshots->ready($examId);
    }
}
