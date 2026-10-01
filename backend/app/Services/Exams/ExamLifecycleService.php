<?php

namespace App\Services\Exams;

use App\Exceptions\Exams\ExamOwnershipException;
use App\Exceptions\Exams\ExamStateException;
use App\Models\Exam;
use App\Services\Academic\SubjectCatalogService;
use App\Services\EntryControl\EntryControlSnapshotService;
use App\Services\Security\AuditLogService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/** Transiciones manuales y automáticas del final del examen. */
class ExamLifecycleService
{
    private ExamTimingService $timing;
    private EntryControlSnapshotService $snapshots;
    private SubjectCatalogService $subjects;
    private AuditLogService $auditLog;

    public function __construct(
        ExamTimingService $timing,
        EntryControlSnapshotService $snapshots,
        SubjectCatalogService $subjects,
        AuditLogService $auditLog
    ) {
        $this->timing = $timing;
        $this->snapshots = $snapshots;
        $this->subjects = $subjects;
        $this->auditLog = $auditLog;
    }

    /** Finaliza inmediatamente un examen activo, únicamente por su docente creador. */
    public function finishManually(Exam $exam): Exam
    {
        $teacherId = $this->subjects->teacherId();
        if ($teacherId === '') {
            throw new RuntimeException('No hay un docente configurado en SCIEM_DOCENTE_FIJO_ID.');
        }

        $exam = DB::transaction(function () use ($exam, $teacherId): Exam {
            $locked = Exam::query()->whereKey($exam->id_examen)->lockForUpdate()->firstOrFail();

            if ((string) $locked->id_usuario_docente !== $teacherId) {
                throw new ExamOwnershipException();
            }

            if (! in_array($locked->estado, [Exam::EN_INGRESO, Exam::EN_CURSO], true)) {
                throw new ExamStateException($this->manualFinishMessage($locked->estado));
            }

            $previousState = $locked->estado;
            $locked->update(['estado' => Exam::FINALIZADO]);

            $this->auditLog->registrar(
                'FINALIZAR',
                'examen',
                ['id_examen' => $locked->id_examen, 'estado' => $previousState],
                ['id_examen' => $locked->id_examen, 'estado' => Exam::FINALIZADO],
                $teacherId
            );

            return $locked;
        });

        $this->deactivateSnapshot((int) $exam->id_examen);

        return $exam->fresh([
            'examType', 'subject', 'career', 'classrooms',
            'groups' => fn ($query) => $query->withStudentCount(),
        ]);
    }

    /**
     * Cierra snapshots al terminar la duración y, dos horas después, persiste
     * FINALIZADO. Devuelve la cantidad de estados modificados.
     */
    public function finishExpired(?Carbon $now = null): int
    {
        $now = $now ?? Carbon::now(config('sciem.zona_horaria'));
        $finished = 0;

        // Reintenta limpiar snapshots si Redis falló después de una finalización manual.
        Exam::query()
            ->where('estado', Exam::FINALIZADO)
            ->whereBetween('fecha', [
                $now->copy()->subDay()->toDateString(),
                $now->copy()->addDay()->toDateString(),
            ])
            ->pluck('id_examen')
            ->each(fn ($examId) => $this->deactivateSnapshot((int) $examId));

        Exam::query()
            ->whereIn('estado', [Exam::PROGRAMADO, Exam::EN_INGRESO, Exam::EN_CURSO])
            ->whereDate('fecha', '<=', $now->toDateString())
            ->orderBy('id_examen')
            ->chunkById(100, function ($exams) use ($now, &$finished): void {
                foreach ($exams as $exam) {
                    if ($exam->duracion === null || ! $this->timing->hasEnded($exam, $now)) {
                        continue;
                    }

                    $this->deactivateSnapshot((int) $exam->id_examen);

                    if ($now->lessThan($this->timing->finishesAutomaticallyAt($exam))) {
                        continue;
                    }

                    $changed = DB::transaction(function () use ($exam, $now): bool {
                        $locked = Exam::query()->whereKey($exam->id_examen)->lockForUpdate()->firstOrFail();
                        if (! in_array($locked->estado, [Exam::PROGRAMADO, Exam::EN_INGRESO, Exam::EN_CURSO], true)
                            || $locked->duracion === null
                            || $now->lessThan($this->timing->finishesAutomaticallyAt($locked))) {
                            return false;
                        }

                        $locked->update(['estado' => Exam::FINALIZADO]);

                        return true;
                    });

                    if ($changed) {
                        $finished++;
                    }
                }
            }, 'id_examen');

        return $finished;
    }

    private function deactivateSnapshot(int $examId): void
    {
        try {
            $this->snapshots->deactivate($examId);
        } catch (Throwable $exception) {
            Log::warning('No se pudo desactivar el snapshot de un examen finalizado', [
                'id_examen' => $examId,
                'motivo' => $exception->getMessage(),
            ]);
        }
    }

    private function manualFinishMessage(string $state): string
    {
        if ($state === Exam::PROGRAMADO) {
            return 'El examen todavía está programado; puede cancelarlo antes de iniciar el ingreso.';
        }

        if ($state === Exam::FINALIZADO) {
            return 'El examen ya está finalizado.';
        }

        if ($state === Exam::CANCELADO) {
            return 'El examen está cancelado y no puede finalizarse.';
        }

        return 'El examen no puede finalizarse en su estado actual.';
    }
}
