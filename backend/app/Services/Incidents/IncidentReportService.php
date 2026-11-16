<?php

namespace App\Services\Incidents;

use App\Exceptions\Exams\ExamStateException;
use App\Models\Action;
use App\Models\EntryAttempt;
use App\Models\Exam;
use App\Models\StudentReport;
use App\Models\User;
use App\Services\EntryControl\EntryAccessService;
use App\Services\EntryControl\LateEntryAccessService;
use App\Services\Security\AuditLogService;
use App\Support\ReportStatus;
use App\Support\Storage\IncidentEvidenceStorage;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use Throwable;

/**
 * Crea el reporte pendiente de un intento rechazado, del control de ingreso (HU-032) o del
 * ingreso con retraso (HU-037); la aprobación pertenece a la HU-045.
 */
class IncidentReportService
{
    public const LATE_ENTRY_NOT_IN_PROGRESS_MESSAGE =
        'El examen no está en curso; ya no se pueden reportar intentos del ingreso con retraso.';

    private EntryAccessService $access;
    private LateEntryAccessService $lateEntryAccess;
    private AuditLogService $audit;
    private IncidentEvidenceStorage $evidence;

    public function __construct(
        EntryAccessService $access,
        LateEntryAccessService $lateEntryAccess,
        AuditLogService $audit,
        IncidentEvidenceStorage $evidence
    ) {
        $this->access = $access;
        $this->lateEntryAccess = $lateEntryAccess;
        $this->audit = $audit;
        $this->evidence = $evidence;
    }

    public function reportAttempt(
        int $examId,
        int $attemptId,
        array $input,
        ?UploadedFile $file,
        User $actor
    ): StudentReport {
        $evidencePath = null;

        try {
            return DB::transaction(function () use ($examId, $attemptId, $input, $file, $actor, &$evidencePath) {
                $exam = Exam::query()->whereKey($examId)->lockForUpdate()->firstOrFail();
                $this->access->assertCanControl($exam, $actor);

                if ($exam->estado !== Exam::EN_INGRESO) {
                    throw new ExamStateException('El control de ingreso no está abierto.');
                }

                $attempt = EntryAttempt::query()
                    ->where('id_examen', $examId)
                    ->whereKey($attemptId)
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->access->assertCanControlRoom($exam, (int) $attempt->id_ambiente, $actor);

                return $this->createAttemptReport($attempt, $input, $file, $actor, $evidencePath);
            });
        } catch (Throwable $exception) {
            throw $this->failureAfterCleanup($exception, $evidencePath);
        }
    }

    /**
     * Reporta un intento rechazado en el ingreso con retraso. La única guarda de tiempo es el
     * estado EN_CURSO, como en HU-042: se puede reportar aunque ya pasó el fin programado.
     *
     * @param array{id_tipo_falta: int, descripcion: string} $input
     */
    public function reportLateEntryAttempt(
        int $examId,
        int $attemptId,
        array $input,
        ?UploadedFile $file,
        User $actor
    ): StudentReport {
        $evidencePath = null;

        try {
            return DB::transaction(function () use ($examId, $attemptId, $input, $file, $actor, &$evidencePath) {
                $exam = Exam::query()->whereKey($examId)->lockForUpdate()->firstOrFail();
                $access = $this->lateEntryAccess->resolve($exam, $actor);

                if ($exam->estado !== Exam::EN_CURSO) {
                    throw new ExamStateException(self::LATE_ENTRY_NOT_IN_PROGRESS_MESSAGE);
                }

                $attempt = EntryAttempt::query()
                    ->where('id_examen', $examId)
                    ->whereKey($attemptId)
                    ->lockForUpdate()
                    ->firstOrFail();

                // El auxiliar solo reporta los intentos de su ambiente; el docente, los de cualquiera.
                $this->lateEntryAccess->controlRoom($exam, $access, (int) $attempt->id_ambiente);

                return $this->createAttemptReport($attempt, $input, $file, $actor, $evidencePath);
            });
        } catch (Throwable $exception) {
            throw $this->failureAfterCleanup($exception, $evidencePath);
        }
    }

    /**
     * Valida el intento y crea el reporte con su evidencia y la bitácora. `$evidencePath` queda
     * con la ruta subida para que quien llama la borre si la transacción falla.
     *
     * @param array{id_tipo_falta: int, descripcion: string} $input
     */
    private function createAttemptReport(
        EntryAttempt $attempt,
        array $input,
        ?UploadedFile $file,
        User $actor,
        ?string &$evidencePath
    ): StudentReport {
        if ($attempt->id_estudiante === null) {
            throw ValidationException::withMessages([
                'estudiante' => ['Solo se puede reportar un intento con estudiante identificado.'],
            ]);
        }

        if ($attempt->studentReport()->exists()) {
            $this->rejectDuplicate();
        }

        if (! Action::query()->where('operacion', 'CREAR')->exists()) {
            throw new LogicException('Falta la acción CREAR requerida por la bitácora.');
        }

        $evidencePath = $file === null ? null : $this->evidence->store($file);

        $report = StudentReport::create([
            'id_estudiante' => $attempt->id_estudiante,
            'id_examen' => $attempt->id_examen,
            'id_intento' => $attempt->id_intento,
            'origen' => 'CONTROL_INGRESO',
            'id_tipo_falta' => $input['id_tipo_falta'],
            'id_usuario_reportante' => $actor->id_usuario,
            'descripcion' => trim($input['descripcion']),
            'evidencia_url' => $evidencePath,
            'evidencia_public_id' => $evidencePath,
            'estado' => ReportStatus::PENDING,
            'fecha_reporte' => now('UTC'),
        ]);

        $this->audit->registrar('CREAR', 'reporte_estudiante', null, $report->toArray(), $actor->id_usuario);

        return $report;
    }

    /**
     * Borra la evidencia subida y traduce el choque del índice único en «ya reportado»; si no,
     * devuelve la excepción original para que quien llama la relance.
     */
    private function failureAfterCleanup(Throwable $exception, ?string $evidencePath): Throwable
    {
        // Los archivos no participan en la transacción de PostgreSQL.
        if ($evidencePath !== null) {
            $this->evidence->delete($evidencePath);
        }

        if (
            $exception instanceof QueryException
            && ($exception->errorInfo[0] ?? null) === '23505'
            && strpos($exception->getMessage(), 'unq_reporte_estudiante_id_intento') !== false
        ) {
            $this->rejectDuplicate();
        }

        return $exception;
    }

    private function rejectDuplicate(): void
    {
        throw ValidationException::withMessages(['id_intento' => ['Este intento ya fue reportado.']]);
    }
}
