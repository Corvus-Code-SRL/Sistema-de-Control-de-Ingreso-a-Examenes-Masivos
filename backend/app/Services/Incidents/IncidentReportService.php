<?php

namespace App\Services\Incidents;

use App\Exceptions\Exams\ExamStateException;
use App\Models\Action;
use App\Models\EntryAttempt;
use App\Models\Exam;
use App\Models\StudentReport;
use App\Models\User;
use App\Services\EntryControl\EntryAccessService;
use App\Services\Security\AuditLogService;
use App\Support\ReportStatus;
use App\Support\Storage\IncidentEvidenceStorage;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use Throwable;

/** Crea el reporte pendiente de un intento; la aprobación pertenece a la HU-045. */
class IncidentReportService
{
    private EntryAccessService $access;
    private AuditLogService $audit;
    private IncidentEvidenceStorage $evidence;

    public function __construct(
        EntryAccessService $access,
        AuditLogService $audit,
        IncidentEvidenceStorage $evidence
    ) {
        $this->access = $access;
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

                $stored = $file === null ? null : $this->evidence->store($file);
                $evidencePath = $stored['path'] ?? null;

                $report = StudentReport::create([
                    'id_estudiante' => $attempt->id_estudiante,
                    'id_examen' => $attempt->id_examen,
                    'id_intento' => $attempt->id_intento,
                    'id_tipo_falta' => $input['id_tipo_falta'],
                    'id_usuario_reportante' => $actor->id_usuario,
                    'descripcion' => trim($input['descripcion']),
                    'evidencia_url' => $stored['url'] ?? null,
                    'evidencia_public_id' => $evidencePath,
                    'estado' => ReportStatus::PENDING,
                    'fecha_reporte' => now('UTC'),
                ]);

                $this->audit->registrar('CREAR', 'reporte_estudiante', null, $report->toArray(), $actor->id_usuario);

                return $report;
            });
        } catch (Throwable $exception) {
            // Los archivos no participan en la transacción de PostgreSQL.
            if ($evidencePath !== null) {
                $this->evidence->delete($evidencePath);
            }

            if ($exception instanceof QueryException
                && ($exception->errorInfo[0] ?? null) === '23505'
                && strpos($exception->getMessage(), 'unq_reporte_estudiante_id_intento') !== false) {
                $this->rejectDuplicate();
            }

            throw $exception;
        }
    }

    private function rejectDuplicate(): void
    {
        throw ValidationException::withMessages(['id_intento' => ['Este intento ya fue reportado.']]);
    }
}