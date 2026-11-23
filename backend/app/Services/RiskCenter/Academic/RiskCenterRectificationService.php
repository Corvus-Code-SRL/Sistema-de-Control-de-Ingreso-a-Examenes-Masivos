<?php

namespace App\Services\RiskCenter\Academic;

use App\Exceptions\RiskCenter\ApprovingTeacherStateException;
use App\Exceptions\RiskCenter\RiskRecordAlreadyRectifiedException;
use App\Models\RectificationRequest;
use App\Models\RiskCenter;
use App\Services\EntryControl\EntryControlSnapshotService;
use App\Services\Security\AuditLogService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Retira registros de la Central de Riesgo de estudiantes (HU-062).
 *
 * Rectificar es retirar el registro de la Central: se marca con quién, cuándo y por qué. La fila nunca se borra
 * ni se reactiva, y el reporte original, su evidencia y sus datos de revisión no se tocan. El actor entra por
 * argumento.
 *
 * Un registro sale de la Central por una de dos vías: el docente que aprobó el reporte acepta una solicitud
 * ({@see RectificationRequestService::accept()}, que usa {@see self::voidLocked()}) o, si ese docente ya no
 * está activo y pasó el plazo configurado, el Administrador lo rectifica sin su confirmación
 * ({@see self::rectifyWithoutConfirmation()}).
 */
class RiskCenterRectificationService
{
    private AuditLogService $auditLog;

    private EntryControlSnapshotService $snapshots;

    private ApprovingTeacherStatusService $teacherStatus;

    public function __construct(
        AuditLogService $auditLog,
        EntryControlSnapshotService $snapshots,
        ApprovingTeacherStatusService $teacherStatus
    ) {
        $this->auditLog = $auditLog;
        $this->snapshots = $snapshots;
        $this->teacherStatus = $teacherStatus;
    }

    /**
     * El Administrador retira el registro sin confirmación porque el docente que lo aprobó ya no está activo y
     * pasaron los días configurados (sciem.rectificacion.dias_docente_inactivo).
     *
     * @throws ModelNotFoundException si el registro no existe
     * @throws RiskRecordAlreadyRectifiedException si ya estaba rectificado
     * @throws ApprovingTeacherStateException si el docente sigue activo o aún no pasó el plazo
     */
    public function rectifyWithoutConfirmation(int $recordId, string $adminId, string $reason): RiskCenter
    {
        $this->recordTeacherDetection($recordId);

        $record = DB::transaction(function () use ($recordId, $adminId, $reason) {
            // Mismo orden de bloqueo que al aceptar una solicitud (solicitud y luego registro): evita un deadlock.
            $closed = $this->closePendingRequests($recordId);
            $record = $this->lockRecord($recordId);
            $closed = array_merge($closed, $this->closePendingRequests($recordId));

            if ($record->isRectified()) {
                throw RiskRecordAlreadyRectifiedException::make();
            }

            $teacherId = $this->approverOf($record);
            $status = $this->teacherStatus->resolve($teacherId, $recordId, true);

            if ($status->active) {
                throw ApprovingTeacherStateException::stillActive();
            }

            if (! $status->canVoidWithoutConfirmation()) {
                throw ApprovingTeacherStateException::inactiveUntil(
                    $status->eligibleFrom ?? $this->waitFromNow()
                );
            }

            $this->voidLocked($record, $adminId, $reason, $adminId, 'RECTIFICAR_SIN_CONFIRMACION', [
                'id_usuario_docente_inactivo' => $teacherId,
                'inactivo_desde' => $status->inactiveSince->toIso8601String(),
                'fuente_inactividad' => $status->source,
                'solicitudes_cerradas' => $closed,
            ]);

            return $record;
        });

        $this->refreshOpenSnapshots((int) $record->id_estudiante);

        return $record;
    }

    /**
     * Marca el registro como rectificado. Debe llamarse dentro de una transacción, con el registro ya bloqueado
     * (FOR UPDATE) por {@see self::lockRecord()}; los antecedentes del control de ingreso se actualizan después
     * del commit con {@see self::refreshOpenSnapshots()}.
     *
     * @param string $requesterAdminId administrador que pidió la rectificación: queda en `id_usuario_rectificador`
     * @param string $actorId quien ejecuta (el docente al aceptar, el administrador en la vía directa)
     * @param string $operation acción de bitácora que firma el retiro
     * @param array<string, mixed> $auditExtra datos que se suman al `nuevo_valor` de la bitácora
     *
     * @throws RiskRecordAlreadyRectifiedException si ya estaba rectificado
     */
    public function voidLocked(
        RiskCenter $record,
        string $requesterAdminId,
        string $reason,
        string $actorId,
        string $operation,
        array $auditExtra = []
    ): void {
        if ($record->isRectified()) {
            throw RiskRecordAlreadyRectifiedException::make();
        }

        $now = Carbon::now();

        // La condición protege aun si el bloqueo no hubiera bastado: solo se rectifica una vez.
        $changed = RiskCenter::withVoided()
            ->where('id_cr_estudiante', $record->id_cr_estudiante)
            ->whereNull('rectificado_en')
            ->update([
                'rectificado_en' => $now,
                'id_usuario_rectificador' => $requesterAdminId,
                'motivo_rectificacion' => $reason,
            ]);

        if ($changed !== 1) {
            throw RiskRecordAlreadyRectifiedException::make();
        }

        $identity = [
            'id_cr_estudiante' => $record->id_cr_estudiante,
            'id_reporte_est' => $record->id_reporte_est,
            'id_estudiante' => $record->id_estudiante,
            'id_examen' => (int) $record->studentReport()->value('id_examen'),
        ];

        $this->auditLog->registrar(
            $operation,
            'central_riesgo',
            $identity + ['estado' => 'ACTIVO'],
            $identity + [
                'estado' => 'RECTIFICADO',
                'motivo_rectificacion' => $reason,
                'rectificado_en' => $now->toIso8601String(),
                'id_usuario_rectificador' => $requesterAdminId,
            ] + $auditExtra,
            $actorId
        );
    }

    /**
     * Cierra como VENCIDA toda solicitud PENDIENTE del registro (queda sin respuesta: no es un rechazo ni una
     * aceptación) para que no quede huérfana cuando el registro sale de la Central por la vía directa. Debe
     * llamarse dentro de una transacción. Devuelve los ids cerrados.
     *
     * @return array<int, int>
     */
    public function closePendingRequests(int $recordId): array
    {
        $ids = RectificationRequest::query()
            ->where('id_cr_estudiante', $recordId)
            ->where('estado', RectificationRequest::PENDING)
            ->orderBy('id_solicitud_rect')
            ->lockForUpdate()
            ->pluck('id_solicitud_rect')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($ids !== []) {
            RectificationRequest::query()
                ->whereIn('id_solicitud_rect', $ids)
                ->update(['estado' => RectificationRequest::EXPIRED]);
        }

        return $ids;
    }

    /**
     * Bloquea la fila del registro: es el punto de serialización, dos operaciones sobre el mismo registro se
     * esperan aquí.
     *
     * @throws ModelNotFoundException si el registro no existe
     */
    public function lockRecord(int $recordId): RiskCenter
    {
        $record = RiskCenter::withVoided()->lockForUpdate()->find($recordId);

        if ($record === null) {
            throw new ModelNotFoundException('No existe el registro de la Central de Riesgo indicado.');
        }

        return $record;
    }

    /** Docente que aprobó el reporte del registro; nulo en datos antiguos sin aprobador. */
    public function approverOf(RiskCenter $record): ?string
    {
        $approver = $record->studentReport()->value('id_usuario_revisor');

        return $approver === null ? null : (string) $approver;
    }

    /**
     * El aviso de antecedentes del control de ingreso se actualiza después de guardar. Un fallo de Redis no
     * deshace una rectificación ya confirmada: se registra y los siguientes snapshots saldrán bien.
     */
    public function refreshOpenSnapshots(int $studentId): void
    {
        try {
            $this->snapshots->refreshBackground($studentId);
        } catch (Throwable $exception) {
            Log::warning('No se actualizaron los antecedentes del control de ingreso tras una rectificación.', [
                'id_estudiante' => $studentId,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Guarda la primera detección de un docente inactivo FUERA de la transacción principal: si esa transacción
     * termina en 409 (plazo sin cumplir), la fecha de detección no se pierde y los N días no se reinician.
     */
    public function recordTeacherDetection(int $recordId): void
    {
        $record = RiskCenter::withVoided()->find($recordId);

        if ($record !== null && ! $record->isRectified()) {
            $this->teacherStatus->resolve($this->approverOf($record), $recordId, true);
        }
    }

    private function waitFromNow(): Carbon
    {
        return Carbon::now()->addDays((int) config('sciem.rectificacion.dias_docente_inactivo', 180));
    }
}
