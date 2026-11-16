<?php

namespace App\Services\RiskCenter\Academic;

use App\Exceptions\RiskCenter\RiskRecordAlreadyRectifiedException;
use App\Models\RiskCenter;
use App\Services\EntryControl\EntryControlSnapshotService;
use App\Services\Security\AuditLogService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Rectifica registros de la Central de Riesgo de estudiantes (HU-062).
 *
 * Rectificar es retirar el registro de la Central: se marca con quién, cuándo y por qué. La fila nunca se borra
 * ni se reactiva, y el reporte original, su evidencia y sus datos de revisión no se tocan. El actor entra por
 * argumento.
 */
class RiskCenterRectificationService
{
    private AuditLogService $auditLog;

    private EntryControlSnapshotService $snapshots;

    public function __construct(AuditLogService $auditLog, EntryControlSnapshotService $snapshots)
    {
        $this->auditLog = $auditLog;
        $this->snapshots = $snapshots;
    }

    /**
     * Retira el registro de la Central.
     *
     * @throws ModelNotFoundException si el registro no existe
     * @throws RiskRecordAlreadyRectifiedException si ya estaba rectificado
     */
    public function rectify(int $recordId, string $adminId, string $reason): RiskCenter
    {
        $record = DB::transaction(function () use ($recordId, $adminId, $reason) {
            // Esta fila es el punto de serialización: dos rectificaciones del mismo registro se esperan aquí.
            $record = RiskCenter::withVoided()->lockForUpdate()->find($recordId);

            if ($record === null) {
                throw new ModelNotFoundException('No existe el registro de la Central de Riesgo indicado.');
            }

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
                    'id_usuario_rectificador' => $adminId,
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
                'RECTIFICAR_REGISTRO',
                'central_riesgo',
                $identity + ['estado' => 'ACTIVO'],
                $identity + [
                    'estado' => 'RECTIFICADO',
                    'motivo_rectificacion' => $reason,
                    'rectificado_en' => $now->toIso8601String(),
                    'id_usuario_rectificador' => $adminId,
                ],
                $adminId
            );

            return $record;
        });

        $this->refreshOpenSnapshots((int) $record->id_estudiante);

        return $record;
    }

    /**
     * El aviso de antecedentes del control de ingreso se actualiza después de guardar. Un fallo de Redis no
     * deshace una rectificación ya confirmada: se registra y los siguientes snapshots saldrán bien.
     */
    private function refreshOpenSnapshots(int $studentId): void
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
}
