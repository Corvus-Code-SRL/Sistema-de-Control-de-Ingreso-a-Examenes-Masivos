<?php

namespace App\Services\Exams;

use App\Exceptions\Exams\ExamOwnershipException;
use App\Exceptions\Exams\ExamStateException;
use App\Models\Exam;
use App\Services\EntryControl\CloseOrdinaryEntryService;
use App\Services\EntryControl\EntryControlSnapshotService;
use App\Services\Security\AuditLogService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Transiciones de estado del ciclo de vida del examen. */
class ExamLifecycleService
{
    private ExamTimingService $timing;
    private EntryControlSnapshotService $snapshots;
    private AuditLogService $auditLog;
    private ExamParticipantService $participants;

    public function __construct(
        ExamTimingService $timing,
        EntryControlSnapshotService $snapshots,
        AuditLogService $auditLog,
        ExamParticipantService $participants
    ) {
        $this->timing = $timing;
        $this->snapshots = $snapshots;
        $this->auditLog = $auditLog;
        $this->participants = $participants;
    }

    /** Inicia el examen a la hora programada, con el control de ingreso ya abierto y sin cerrarlo. */
    public function startExpired(?Carbon $now = null): int
    {
        $now = $now ?? Carbon::now(config('sciem.zona_horaria'));
        $started = 0;

        Exam::query()
            ->where('estado', Exam::PROGRAMADO)
            ->whereDate('fecha', '<=', $now->copy()->addDay()->toDateString())
            ->orderBy('id_examen')
            ->chunkById(100, function ($exams) use ($now, &$started): void {
                foreach ($exams as $exam) {
                    if ($exam->duracion === null
                        || $now->lessThan($this->timing->startsAt($exam))
                        || $this->timing->hasEnded($exam, $now)) {
                        continue;
                    }

                    try {
                        $changed = DB::transaction(function () use ($exam, $now): bool {
                            $locked = Exam::query()->whereKey($exam->id_examen)->lockForUpdate()->firstOrFail();
                            if ($locked->estado !== Exam::PROGRAMADO
                                || $locked->duracion === null
                                || ! CloseOrdinaryEntryService::hasOpened((int) $locked->id_examen)
                                || $now->lessThan($this->timing->startsAt($locked))
                                || $this->timing->hasEnded($locked, $now)) {
                                return false;
                            }

                            $locked->estado = Exam::EN_CURSO;
                            $locked->save();

                            $this->auditLog->registrar(
                                'INICIAR',
                                'examen',
                                ['id_examen' => $locked->id_examen, 'estado' => Exam::PROGRAMADO],
                                [
                                    'id_examen' => $locked->id_examen,
                                    'estado' => Exam::EN_CURSO,
                                    'tipo_inicio' => 'AUTOMATICO',
                                ],
                                (string) $locked->id_usuario_docente
                            );

                            return true;
                        });

                        if ($changed) {
                            $started++;
                        }
                    } catch (Throwable $exception) {
                        Log::error('Error al iniciar automáticamente un examen', [
                            'id_examen' => $exam->id_examen,
                            'motivo' => $exception->getMessage(),
                        ]);
                    }
                }
            }, 'id_examen');

        return $started;
    }

    /** Finaliza un examen en curso por decisión de su docente propietario. */
    public function finishManually(Exam $exam, string $teacherId): Exam
    {
        $exam = DB::transaction(function () use ($exam, $teacherId): Exam {
            $locked = Exam::query()->whereKey($exam->id_examen)->lockForUpdate()->firstOrFail();

            if ((string) $locked->id_usuario_docente !== $teacherId) {
                throw new ExamOwnershipException();
            }

            if ($locked->estado !== Exam::EN_CURSO) {
                throw new ExamStateException($this->manualFinishMessage($locked->estado));
            }

            $this->finishLockedExam($locked, Carbon::now('UTC'), false);

            return $locked;
        });

        $this->deactivateSnapshot((int) $exam->id_examen);

        $exam = $exam->fresh([
            'examType', 'subject', 'career', 'classrooms',
            'groups' => fn ($query) => $query->withStudentCount(),
        ]);

        $this->participants->applyFrozenStudentCounts([$exam]);

        return $exam;
    }

    /**
     * Cierra snapshots al terminar la duración y finaliza al cumplirse el margen.
     * Devuelve la cantidad de estados modificados.
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
            ->whereIn('estado', [Exam::PROGRAMADO, Exam::EN_CURSO])
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

                    try {
                        $changed = DB::transaction(function () use ($exam, $now): bool {
                            $locked = Exam::query()->whereKey($exam->id_examen)->lockForUpdate()->firstOrFail();
                            if (! in_array($locked->estado, [Exam::PROGRAMADO, Exam::EN_CURSO], true)
                                || $locked->duracion === null) {
                                return false;
                            }

                            $deadline = $this->timing->finishesAutomaticallyAt($locked);

                            if ($now->lessThan($deadline)) {
                                return false;
                            }

                            // El fin real del cierre automático es su plazo, no el momento en que corrió el planificador.
                            $this->finishLockedExam($locked, $deadline->copy()->utc(), true);

                            return true;
                        });

                        if ($changed) {
                            $finished++;
                        }
                    } catch (Throwable $exception) {
                        Log::error('Error al finalizar automáticamente un examen', [
                            'id_examen' => $exam->id_examen,
                            'motivo' => $exception->getMessage(),
                        ]);
                    }
                }
            }, 'id_examen');

        return $finished;
    }

    private function finishLockedExam(Exam $exam, Carbon $finishedAt, bool $automatic): void
    {
        $previousState = $exam->estado;

        $this->registerMissingStudents((int) $exam->id_examen);
        $exam->estado = Exam::FINALIZADO;
        $exam->fin_real_en = $finishedAt;
        $exam->fin_automatico = $automatic;
        if ($exam->ingreso_abierto_en !== null && $exam->ingreso_ordinario_cerrado_en === null) {
            $exam->ingreso_ordinario_cerrado_en = $finishedAt;
        }
        $exam->save();
        app(ExamInvitationExpiryService::class)->voidPendingForExam((int) $exam->id_examen);

        $newValues = ['id_examen' => $exam->id_examen, 'estado' => Exam::FINALIZADO];
        if ($automatic) {
            $newValues['tipo_finalizacion'] = 'AUTOMATICA';
        }

        $this->auditLog->registrar(
            'FINALIZAR',
            'examen',
            ['id_examen' => $exam->id_examen, 'estado' => $previousState],
            $newValues,
            (string) $exam->id_usuario_docente
        );
    }

    private function registerMissingStudents(int $examId): void
    {
        DB::statement(
            "
            INSERT INTO examen_estudiante (
                id_examen,
                id_estudiante,
                id_grupo,
                estado_habilitacion,
                estado_ingreso,
                hora_ingreso
            )
            SELECT
                gx.id_examen,
                gs.id_estudiante,
                MIN(gs.id_grupo),
                'HABILITADO',
                'NO_INGRESO',
                NULL
            FROM grupo_examen gx
            INNER JOIN grupo_estudiante gs
                ON gs.id_grupo = gx.id_grupo
            WHERE gx.id_examen = ?
            GROUP BY gx.id_examen, gs.id_estudiante
            ON CONFLICT (id_examen, id_estudiante) DO NOTHING
            ",
            [$examId]
        );
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
