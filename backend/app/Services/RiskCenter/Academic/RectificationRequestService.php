<?php

namespace App\Services\RiskCenter\Academic;

use App\Exceptions\RiskCenter\ApprovingTeacherStateException;
use App\Exceptions\RiskCenter\RectificationRequestClosedException;
use App\Exceptions\RiskCenter\RectificationRequestOwnershipException;
use App\Exceptions\RiskCenter\RectificationRequestPendingException;
use App\Exceptions\RiskCenter\RiskRecordAlreadyRectifiedException;
use App\Models\RectificationRequest;
use App\Services\Security\AuditLogService;
use App\Support\Storage\RectificationEvidenceStorage;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Doble confirmación de la rectificación de la Central de Riesgo (HU-062).
 *
 * El Administrador envía una solicitud al docente que aprobó el reporte; el registro sigue activo hasta que ese
 * docente la acepta. Rechazar o dejarla vencer no cambia la Central. Cada operación es una transacción con
 * bloqueos de fila (la solicitud y el registro, en ese orden) y el actor entra por argumento.
 */
class RectificationRequestService
{
    private const TABLE = 'solicitud_rectificacion';

    private RiskCenterRectificationService $rectifications;

    private ApprovingTeacherStatusService $teacherStatus;

    private RectificationExpiryService $expiry;

    private RectificationEvidenceStorage $evidence;

    private AuditLogService $auditLog;

    public function __construct(
        RiskCenterRectificationService $rectifications,
        ApprovingTeacherStatusService $teacherStatus,
        RectificationExpiryService $expiry,
        RectificationEvidenceStorage $evidence,
        AuditLogService $auditLog
    ) {
        $this->rectifications = $rectifications;
        $this->teacherStatus = $teacherStatus;
        $this->expiry = $expiry;
        $this->evidence = $evidence;
        $this->auditLog = $auditLog;
    }

    /**
     * Envía la solicitud al docente que aprobó el reporte. El registro NO se retira.
     *
     * @throws ModelNotFoundException si el registro no existe
     * @throws RiskRecordAlreadyRectifiedException si ya estaba rectificado
     * @throws ApprovingTeacherStateException si el docente ya no está activo (nadie podría responderla)
     * @throws RectificationRequestPendingException si ya hay una pendiente vigente
     */
    public function send(int $recordId, string $adminId, string $reason): RectificationRequest
    {
        $this->rectifications->recordTeacherDetection($recordId);

        return DB::transaction(function () use ($recordId, $adminId, $reason) {
            $record = $this->rectifications->lockRecord($recordId);

            if ($record->isRectified()) {
                throw RiskRecordAlreadyRectifiedException::make();
            }

            // Una pendiente ya vencida ocuparía el índice único aunque la tarea programada no haya corrido.
            $this->expiry->expireForRecord($recordId);

            $teacherId = $this->rectifications->approverOf($record);
            $status = $this->teacherStatus->resolve($teacherId, $recordId, true);

            if (! $status->active) {
                throw $status->canVoidWithoutConfirmation()
                    ? ApprovingTeacherStateException::inactiveCanVoidDirectly()
                    : ApprovingTeacherStateException::inactiveUntil($status->eligibleFrom ?? $this->waitFromNow());
            }

            if (RectificationRequest::query()->where('id_cr_estudiante', $recordId)
                ->where('estado', RectificationRequest::PENDING)->exists()) {
                throw RectificationRequestPendingException::make();
            }

            $now = Carbon::now();

            try {
                $request = RectificationRequest::query()->create([
                    'id_cr_estudiante' => $recordId,
                    'id_usuario_solicitante' => $adminId,
                    'motivo_solicitud' => $reason,
                    'id_usuario_docente' => $teacherId,
                    'estado' => RectificationRequest::PENDING,
                    'fecha_solicitud' => $now,
                    'vence_en' => $now->copy()->addDays((int) config('sciem.rectificacion.dias_vencimiento', 7)),
                ]);
            } catch (QueryException $exception) {
                // Índice único parcial: respaldo del bloqueo si dos envíos llegaran a coincidir.
                if ((string) $exception->getCode() === '23505') {
                    throw RectificationRequestPendingException::make();
                }

                throw $exception;
            }

            $this->auditLog->registrar(
                'SOLICITAR_RECTIFICACION',
                self::TABLE,
                null,
                $this->snapshot($request) + ['motivo_solicitud' => $reason],
                $adminId
            );

            return $request;
        });
    }

    /**
     * El docente acepta: el registro sale de la Central tal como en la rectificación directa, con el
     * administrador solicitante y su motivo, y la solicitud queda ACEPTADA.
     *
     * @throws ModelNotFoundException si la solicitud no existe
     * @throws RectificationRequestOwnershipException si quien responde no es el docente aprobador
     * @throws RectificationRequestClosedException si venció o ya fue respondida
     * @throws RiskRecordAlreadyRectifiedException si el registro ya no está activo
     */
    public function accept(int $requestId, string $teacherId): RectificationRequest
    {
        $this->expiry->expireOne($requestId);

        $studentId = 0;

        try {
            $request = $this->acceptInTransaction($requestId, $teacherId, $studentId);
        } catch (RiskRecordAlreadyRectifiedException $exception) {
            $this->closeOrphan($requestId);

            throw $exception;
        }

        $this->rectifications->refreshOpenSnapshots($studentId);

        return $request;
    }

    private function acceptInTransaction(int $requestId, string $teacherId, int &$studentId): RectificationRequest
    {
        return DB::transaction(function () use ($requestId, $teacherId, &$studentId) {
            [$request, $record] = $this->lockAnswerable($requestId, $teacherId);
            $studentId = (int) $record->id_estudiante;
            $before = $this->snapshot($request);
            $now = Carbon::now();

            $request->forceFill(['estado' => RectificationRequest::ACCEPTED, 'fecha_respuesta' => $now])->save();

            $this->rectifications->voidLocked(
                $record,
                (string) $request->id_usuario_solicitante,
                (string) $request->motivo_solicitud,
                $teacherId,
                'RECTIFICAR_REGISTRO',
                ['id_usuario_confirmador' => $teacherId, 'id_solicitud_rect' => $request->id_solicitud_rect]
            );

            $this->auditLog->registrar(
                'ACEPTAR_RECTIFICACION',
                self::TABLE,
                $before,
                $this->snapshot($request) + ['fecha_respuesta' => $now->toIso8601String()],
                $teacherId
            );

            return $request;
        });
    }

    /**
     * El docente rechaza con un motivo y, opcionalmente, una imagen: la solicitud queda RECHAZADA y el registro
     * sigue en la Central.
     *
     * @throws ModelNotFoundException si la solicitud no existe
     * @throws RectificationRequestOwnershipException si quien responde no es el docente aprobador
     * @throws RectificationRequestClosedException si venció o ya fue respondida
     * @throws RiskRecordAlreadyRectifiedException si el registro ya no está activo
     */
    public function reject(
        int $requestId,
        string $teacherId,
        string $reason,
        ?UploadedFile $image
    ): RectificationRequest {
        $this->expiry->expireOne($requestId);

        $storedPath = null;

        try {
            return DB::transaction(function () use ($requestId, $teacherId, $reason, $image, &$storedPath) {
                [$request] = $this->lockAnswerable($requestId, $teacherId);
                $before = $this->snapshot($request);
                $now = Carbon::now();

                // Se guarda recién con todas las validaciones superadas: un 409 no deja archivos sueltos.
                $storedPath = $image === null ? null : $this->evidence->store($image);

                $request->forceFill([
                    'estado' => RectificationRequest::REJECTED,
                    'fecha_respuesta' => $now,
                    'motivo_rechazo' => $reason,
                    'evidencia_public_id' => $storedPath,
                ])->save();

                $this->auditLog->registrar(
                    'RECHAZAR_RECTIFICACION',
                    self::TABLE,
                    $before,
                    $this->snapshot($request) + [
                        'fecha_respuesta' => $now->toIso8601String(),
                        'motivo_rechazo' => $reason,
                        'con_evidencia' => $storedPath !== null,
                    ],
                    $teacherId
                );

                return $request;
            });
        } catch (Throwable $exception) {
            if ($storedPath !== null) {
                $this->evidence->delete($storedPath);
            }

            if ($exception instanceof RiskRecordAlreadyRectifiedException) {
                $this->closeOrphan($requestId);
            }

            throw $exception;
        }
    }

    /**
     * El registro ya salió de la Central y la solicitud seguía PENDIENTE: se cierra como VENCIDA en una
     * transacción corta ya confirmada, porque el 409 revierte la de la respuesta.
     */
    private function closeOrphan(int $requestId): void
    {
        RectificationRequest::query()
            ->where('id_solicitud_rect', $requestId)
            ->where('estado', RectificationRequest::PENDING)
            ->update(['estado' => RectificationRequest::EXPIRED]);
    }

    /**
     * Bloquea la solicitud y luego el registro (siempre en ese orden) y verifica que se pueda responder:
     * existe, es de este docente, está pendiente y vigente, y el registro sigue activo.
     *
     * @return array{0: RectificationRequest, 1: \App\Models\RiskCenter}
     */
    private function lockAnswerable(int $requestId, string $teacherId): array
    {
        $request = RectificationRequest::query()->lockForUpdate()->find($requestId);

        if ($request === null) {
            throw new ModelNotFoundException('No existe la solicitud de rectificación indicada.');
        }

        if ((string) $request->id_usuario_docente !== $teacherId) {
            throw new RectificationRequestOwnershipException();
        }

        if ($request->effectiveState() === RectificationRequest::EXPIRED) {
            throw RectificationRequestClosedException::expired();
        }

        if ($request->estado !== RectificationRequest::PENDING) {
            throw RectificationRequestClosedException::answered();
        }

        $record = $this->rectifications->lockRecord((int) $request->id_cr_estudiante);

        if ($record->isRectified()) {
            throw RiskRecordAlreadyRectifiedException::make();
        }

        return [$request, $record];
    }

    /** @return array<string, mixed> */
    private function snapshot(RectificationRequest $request): array
    {
        return [
            'id_solicitud_rect' => $request->id_solicitud_rect,
            'id_cr_estudiante' => $request->id_cr_estudiante,
            'id_usuario_solicitante' => $request->id_usuario_solicitante,
            'id_usuario_docente' => $request->id_usuario_docente,
            'estado' => $request->estado,
            'fecha_solicitud' => $request->fecha_solicitud->toIso8601String(),
            'vence_en' => $request->vence_en->toIso8601String(),
        ];
    }

    private function waitFromNow(): Carbon
    {
        return Carbon::now()->addDays((int) config('sciem.rectificacion.dias_docente_inactivo', 180));
    }
}
