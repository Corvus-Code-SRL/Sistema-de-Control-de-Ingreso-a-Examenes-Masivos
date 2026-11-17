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
     * Valida la capacidad antes de abrir; las aulas se calculan al verificar.
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

            $rooms = DB::table('examen_ambiente as ea')
                ->join('ambiente as a', 'a.id_ambiente', '=', 'ea.id_ambiente')
                ->where('ea.id_examen', $examId)
                ->selectRaw('count(*) as room_count, coalesce(sum(a.capacidad), 0) as capacity')
                ->first();

            if ((int) $rooms->room_count === 0) {
                throw new EntryOpeningException(
                    EntryOpeningException::MISSING_ROOMS,
                    'El examen no tiene ambientes asignados.'
                );
            }

            $capacity = (int) $rooms->capacity;
            if ($capacity < $studentCount) {
                throw new EntryOpeningException(
                    EntryOpeningException::INSUFFICIENT_CAPACITY,
                    sprintf('Capacidad %d menor que la nómina de %d.', $capacity, $studentCount)
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
     * Ordena estudiantes y aulas por ID. El lugar del estudiante en la nómina
     * determina en qué tramo de capacidades acumuladas cae, sin guardar copias.
     * Solo se llama después de comprobar su pertenencia al examen.
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
