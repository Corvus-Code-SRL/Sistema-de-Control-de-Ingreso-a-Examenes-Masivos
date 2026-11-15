<?php

namespace App\Services\RiskCenter\Academic;

use App\Exceptions\RiskCenter\RiskRectificationConflictException;
use App\Models\RiskCenter;
use App\Models\RiskCenterVersion;
use App\Services\EntryControl\EntryControlSnapshotService;
use App\Services\Security\AuditLogService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Rectifica registros confirmados de la Central de Riesgo (HU-062).
 *
 * El registro confirmado nunca se sobrescribe: cada rectificación agrega una versión vigente y la
 * anterior pasa a ser histórica. Solo se corrigen el tipo de falta y la descripción; el estudiante, el
 * examen, la fecha, la evidencia, quien reportó y quien aprobó no cambian. El actor entra por argumento.
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
     * Crea la nueva versión vigente. `$baseVersion` es el número de la versión que la persona vio al abrir
     * el formulario: si ya cambió, otra rectificación se adelantó.
     *
     * @throws ModelNotFoundException
     * @throws RiskRectificationConflictException
     */
    public function rectify(
        int $recordId,
        string $adminId,
        int $faultTypeId,
        ?string $description,
        string $reason,
        int $baseVersion
    ): RiskCenter {
        $record = DB::transaction(function () use (
            $recordId,
            $adminId,
            $faultTypeId,
            $description,
            $reason,
            $baseVersion
        ) {
            // Esta fila es el punto de serialización: dos rectificaciones del mismo registro se esperan aquí.
            $record = RiskCenter::query()->lockForUpdate()->find($recordId);

            if ($record === null) {
                throw new ModelNotFoundException('No existe el registro de la Central de Riesgo indicado.');
            }

            $report = $record->studentReport()->first();
            $current = $record->currentVersion()->first();
            $currentNumber = $current === null ? 1 : $current->numero_version;
            $currentTypeId = $current === null ? (int) $report->id_tipo_falta : $current->id_tipo_falta;
            $currentDescription = $this->clean($current === null ? $report->descripcion : $current->descripcion);
            $description = $this->clean($description);

            if ($baseVersion !== $currentNumber) {
                throw RiskRectificationConflictException::staleVersion();
            }

            if ($faultTypeId === $currentTypeId && $description === $currentDescription) {
                throw RiskRectificationConflictException::unchanged();
            }

            if ($current === null) {
                RiskCenterVersion::query()->create([
                    'id_cr_estudiante' => $record->id_cr_estudiante,
                    'numero_version' => 1,
                    'id_tipo_falta' => $currentTypeId,
                    'descripcion' => $currentDescription,
                    'motivo' => null,
                    'id_usuario_autor' => $report->id_usuario_revisor ?? $report->id_usuario_reportante,
                    'fecha_registro' => $record->fecha_registro,
                    'es_vigente' => false,
                ]);
            } else {
                // Es lo único de una versión guardada que cambia: deja de ser la vigente.
                RiskCenterVersion::query()
                    ->where('id_version', $current->id_version)
                    ->update(['es_vigente' => false]);
            }

            $new = RiskCenterVersion::query()->create([
                'id_cr_estudiante' => $record->id_cr_estudiante,
                'numero_version' => $currentNumber + 1,
                'id_tipo_falta' => $faultTypeId,
                'descripcion' => $description,
                'motivo' => $reason,
                'id_usuario_autor' => $adminId,
                'fecha_registro' => Carbon::now(),
                'es_vigente' => true,
            ]);

            $this->auditLog->registrar(
                'RECTIFICAR_REGISTRO',
                'central_riesgo',
                [
                    'id_cr_estudiante' => $record->id_cr_estudiante,
                    'id_estudiante' => $record->id_estudiante,
                    'numero_version' => $currentNumber,
                    'id_tipo_falta' => $currentTypeId,
                    'descripcion' => $currentDescription,
                ],
                [
                    'id_cr_estudiante' => $record->id_cr_estudiante,
                    'id_estudiante' => $record->id_estudiante,
                    'numero_version' => $new->numero_version,
                    'id_tipo_falta' => $new->id_tipo_falta,
                    'descripcion' => $new->descripcion,
                    'motivo' => $new->motivo,
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
     * deshace una rectificación ya confirmada: se registra y el siguiente snapshot saldrá bien.
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

    private function clean(?string $text): ?string
    {
        $text = $text === null ? '' : trim($text);

        return $text === '' ? null : $text;
    }
}
