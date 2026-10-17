<?php

namespace App\Services\Incidents;

use App\Exceptions\Incidents\IncidentAlreadyReviewedException;
use App\Exceptions\Incidents\IncidentOwnershipException;
use App\Exceptions\Incidents\IncidentReportNotFoundException;
use App\Models\RiskCenter;
use App\Models\StudentReport;
use App\Services\Security\AuditLogService;
use App\Support\ReportStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Lista, encuentra y revisa reportes de estudiante (HU-045).
 *
 * El actor entra por argumento: este servicio nunca resuelve el usuario actual
 * (ServicesDoNotResolveCurrentUserTest). La autorización por dueño del examen se
 * aplica aquí con assertOwnedBy, igual que ExamService.
 */
class IncidentReportService
{
    private AuditLogService $auditLog;

    public function __construct(AuditLogService $auditLog)
    {
        $this->auditLog = $auditLog;
    }

    /**
     * Reportes del docente, filtrados por estado y opcionalmente por examen.
     *
     * @return array{data: array<int, array>, meta: array{total_pendientes: int, total_revisadas: int}}
     */
    public function list(string $teacherId, string $estado, ?int $examId): array
    {
        $reports = $this->baseQuery($teacherId)
            ->when($examId !== null, fn (Builder $q) => $q->where('id_examen', $examId))
            ->when($estado === 'pendiente', fn (Builder $q) => $q->where('estado', ReportStatus::PENDING))
            ->when($estado === 'revisada', fn (Builder $q) => $q->whereIn('estado', [ReportStatus::APPROVED, ReportStatus::REJECTED]))
            ->orderBy('fecha_reporte', $estado === 'pendiente' ? 'asc' : 'desc')
            ->get();

        return [
            'data' => $reports->map(fn (StudentReport $r) => $this->toDto($r))->all(),
            'meta' => [
                'total_pendientes' => $this->countPending($teacherId),
                'total_revisadas'  => $this->countRevisadas($teacherId),
            ],
        ];
    }

    /**
     * Detalle de un reporte propio. Lanza 404 si no existe, 403 si no es suyo.
     *
     * @throws IncidentReportNotFoundException
     * @throws IncidentOwnershipException
     */
    public function find(string $teacherId, string $reporte): array
    {
        $report = $this->resolveReport($teacherId, $reporte);

        return $this->toDto($report);
    }

    /**
     * Aprueba el reporte y lo ingresa a la Central de Riesgo en la misma transacción.
     *
     * @throws IncidentReportNotFoundException
     * @throws IncidentOwnershipException
     * @throws IncidentAlreadyReviewedException
     */
    public function approve(string $teacherId, string $reporte, ?string $observacion): array
    {
        return DB::transaction(function () use ($teacherId, $reporte, $observacion) {
            $report = $this->lockReport($teacherId, $reporte);

            $this->assertPending($report);

            $report->update([
                'estado'               => ReportStatus::APPROVED,
                'fecha_revision'       => Carbon::now(),
                'id_usuario_revisor'   => $teacherId,
                'observacion_revision' => $observacion,
            ]);

            RiskCenter::create([
                'id_reporte_est' => $report->id_reporte_est,
                'id_estudiante'  => $report->id_estudiante,
            ]);

            $this->auditLog->registrar(
                'APROBAR_REPORTE',
                'reporte_estudiante',
                ['id_reporte_est' => $report->id_reporte_est, 'estado' => ReportStatus::PENDING],
                ['id_reporte_est' => $report->id_reporte_est, 'estado' => ReportStatus::APPROVED],
                $teacherId
            );

            return $this->toDto($report->fresh($this->listRelations()));
        });
    }

    /**
     * Rechaza el reporte. No entra a la Central de Riesgo.
     *
     * @throws IncidentReportNotFoundException
     * @throws IncidentOwnershipException
     * @throws IncidentAlreadyReviewedException
     */
    public function reject(string $teacherId, string $reporte, string $observacion): array
    {
        return DB::transaction(function () use ($teacherId, $reporte, $observacion) {
            $report = $this->lockReport($teacherId, $reporte);

            $this->assertPending($report);

            $report->update([
                'estado'               => ReportStatus::REJECTED,
                'fecha_revision'       => Carbon::now(),
                'id_usuario_revisor'   => $teacherId,
                'observacion_revision' => $observacion,
            ]);

            $this->auditLog->registrar(
                'RECHAZAR_REPORTE',
                'reporte_estudiante',
                ['id_reporte_est' => $report->id_reporte_est, 'estado' => ReportStatus::PENDING],
                ['id_reporte_est' => $report->id_reporte_est, 'estado' => ReportStatus::REJECTED],
                $teacherId
            );

            return $this->toDto($report->fresh($this->listRelations()));
        });
    }

    /* ===================================================================== */

    private function baseQuery(string $teacherId): Builder
    {
        return StudentReport::query()
            ->whereHas('exam', fn (Builder $q) => $q->where('id_usuario_docente', $teacherId))
            ->with($this->listRelations());
    }

    private function countPending(string $teacherId): int
    {
        return StudentReport::query()
            ->whereHas('exam', fn (Builder $q) => $q->where('id_usuario_docente', $teacherId))
            ->where('estado', ReportStatus::PENDING)
            ->count();
    }

    private function countRevisadas(string $teacherId): int
    {
        return StudentReport::query()
            ->whereHas('exam', fn (Builder $q) => $q->where('id_usuario_docente', $teacherId))
            ->whereIn('estado', [ReportStatus::APPROVED, ReportStatus::REJECTED])
            ->count();
    }

    /**
     * Encuentra el reporte por el identificador compuesto `estudiante-{id}`.
     * Lanza 404 si no existe, 403 si existe pero el examen no es del docente.
     *
     * @throws IncidentReportNotFoundException
     * @throws IncidentOwnershipException
     */
    private function resolveReport(string $teacherId, string $reporte): StudentReport
    {
        $id = $this->parseReportId($reporte);

        $report = StudentReport::query()
            ->with($this->listRelations())
            ->where('id_reporte_est', $id)
            ->first();

        if ($report === null) {
            throw new IncidentReportNotFoundException();
        }

        $this->assertOwnedBy($report, $teacherId);

        return $report;
    }

    /** Igual que resolveReport pero con lockForUpdate para la revisión. */
    private function lockReport(string $teacherId, string $reporte): StudentReport
    {
        $id = $this->parseReportId($reporte);

        $report = StudentReport::query()
            ->where('id_reporte_est', $id)
            ->lockForUpdate()
            ->first();

        if ($report === null) {
            throw new IncidentReportNotFoundException();
        }

        $this->assertOwnedBy($report, $teacherId);

        return $report;
    }

    private function parseReportId(string $reporte): int
    {
        if (preg_match('/^estudiante-([0-9]{1,9})$/', $reporte, $m) !== 1) {
            throw new IncidentReportNotFoundException();
        }

        return (int) $m[1];
    }

    /** @throws IncidentOwnershipException */
    private function assertOwnedBy(StudentReport $report, string $teacherId): void
    {
        $ownerId = $report->exam?->id_usuario_docente;

        if ((string) $ownerId !== $teacherId) {
            throw new IncidentOwnershipException();
        }
    }

    /** @throws IncidentAlreadyReviewedException */
    private function assertPending(StudentReport $report): void
    {
        if ($report->estado !== ReportStatus::PENDING) {
            throw new IncidentAlreadyReviewedException();
        }
    }

    private function listRelations(): array
    {
        return [
            'student',
            'exam',
            'exam.subject',
            'incidentType',
            'userReporter',
            'userReviewer',
            'entryAttempt.classroom',
            'riskCenter',
        ];
    }

    /* ===================================================================== */
    /* DTO                                                                   */
    /* ===================================================================== */

    private function toDto(StudentReport $r): array
    {
        $exam = $r->exam;
        $evidencia = $this->evidenciaDto($r);

        return [
            'id'     => 'estudiante-' . $r->id_reporte_est,
            'tipo'   => 'estudiante',
            'estado' => $r->estado,

            'persona' => [
                'nombre_completo' => $r->student
                    ? trim("{$r->student->nombre} {$r->student->apellido_paterno} {$r->student->apellido_materno}")
                    : '',
                'codigo'  => $r->student->cod_sis ?? '',
                'grupo'   => null,
                'carrera' => null,
            ],

            'examen' => [
                'id_examen'          => $exam->id_examen,
                'nombre_examen'      => $exam->nombre_examen,
                'fecha'              => $exam->fecha?->format('Y-m-d'),
                'id_usuario_docente' => $exam->id_usuario_docente,
                'materia'            => $exam->subject?->nombre ?? null,
            ],

            'tipo_falta' => [
                'id'     => $r->incidentType->id_falta,
                'nombre' => $r->incidentType->nombre,
            ],

            'descripcion'   => $r->descripcion,
            'evidencia_url' => $evidencia['url'] ?? null,
            'evidencia'     => $evidencia,

            'reportante' => [
                'nombre_completo' => $r->userReporter
                    ? trim("{$r->userReporter->nombre} {$r->userReporter->apellido_paterno}")
                    : '',
                'rol' => $r->userReporter?->rolActivo()?->nombre_rol ?? '',
            ],

            'fecha_reporte' => $r->fecha_reporte?->toIso8601String(),
            'hora_reporte'  => $r->fecha_reporte?->format('H:i'),

            'ambiente' => $r->entryAttempt?->classroom
                ? [
                    'id_ambiente' => $r->entryAttempt->classroom->id_ambiente,
                    'nro_aula'    => $r->entryAttempt->classroom->nro_aula,
                ]
                : null,

            'intento' => $r->entryAttempt
                ? [
                    'id_intento'     => $r->entryAttempt->id_intento,
                    'motivo'         => $r->entryAttempt->motivo,
                    'motivo_legible' => $this->motivoLegible($r->entryAttempt->motivo),
                    'observacion'    => $r->entryAttempt->observacion,
                    'fecha_intento'  => $r->entryAttempt->registrado_en?->utc()->toIso8601String(),
                    'hora_intento'   => $r->entryAttempt->registrado_en?->utc()->format('H:i'),
                    'ambiente'       => $r->entryAttempt->classroom
                        ? [
                            'id_ambiente' => $r->entryAttempt->classroom->id_ambiente,
                            'nro_aula'    => $r->entryAttempt->classroom->nro_aula,
                        ]
                        : null,
                ]
                : null,

            'revisor' => $r->userReviewer
                ? [
                    'nombre_completo'      => trim("{$r->userReviewer->nombre} {$r->userReviewer->apellido_paterno}"),
                    'fecha_revision'       => $r->fecha_revision?->toIso8601String(),
                    'observacion_revision' => $r->observacion_revision,
                ]
                : null,

            'en_central_riesgo' => $r->riskCenter !== null,
        ];
    }

    /**
     * Resuelve el archivo de evidencia con el APP_URL actual, ignorando la URL guardada
     * (que puede tener un host viejo). El peso y el tipo se calculan al vuelo.
     */
    private function evidenciaDto(StudentReport $r): ?array
    {
        if ($r->evidencia_public_id === null) {
            return null;
        }

        $disk = Storage::disk('public');
        $publicId = $r->evidencia_public_id;
        $extension = strtolower(pathinfo($publicId, PATHINFO_EXTENSION));

        $tipo = match ($extension) {
            'png', 'jpg', 'jpeg', 'gif', 'webp' => 'imagen',
            'pdf'                                => 'PDF',
            default                              => 'archivo',
        };

        $bytes = $disk->exists($publicId) ? $disk->size($publicId) : null;

        return [
            'url'    => $disk->url($publicId),
            'nombre' => basename($publicId),
            'tipo'   => $tipo,
            'peso'   => $bytes !== null ? $this->formatBytes($bytes) : null,
        ];
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return "{$bytes} B";
        }

        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }

        return round($bytes / (1024 * 1024), 1) . ' MB';
    }

    private function motivoLegible(string $motivo): string
    {
        return match ($motivo) {
            'DOCUMENTO_NO_VALIDO'  => 'El documento presentado no es válido.',
            'IDENTIDAD_DUDOSA'     => 'La identidad del estudiante es dudosa.',
            'DECISION_CONTROLADOR' => 'Decisión del controlador en la puerta.',
            'NO_ENCONTRADO'        => 'El estudiante no figura en el sistema.',
            'OTRO'                 => 'Otro motivo.',
            default                => $motivo,
        };
    }
}