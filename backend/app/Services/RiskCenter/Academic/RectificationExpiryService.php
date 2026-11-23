<?php

namespace App\Services\RiskCenter\Academic;

use App\Models\RectificationRequest;
use Carbon\Carbon;

/**
 * Marca como VENCIDAS las solicitudes de rectificación que pasaron su plazo sin respuesta (HU-062).
 *
 * El vencimiento no es una decisión de nadie: no se anula el registro, no cuenta como rechazo y no deja fila en
 * la bitácora. Lo ejecuta la tarea programada y, como red de seguridad, se repite antes de enviar o responder
 * una solicitud; las lecturas además tratan como vencida a toda PENDIENTE pasada de fecha
 * ({@see RectificationRequest::effectiveState()}).
 */
class RectificationExpiryService
{
    /** Marca todas las vencidas. Devuelve cuántas cambió. */
    public function expireDue(): int
    {
        return $this->expiredQuery()->update(['estado' => RectificationRequest::EXPIRED]);
    }

    /** Marca las vencidas de un registro; libera el índice único de «una pendiente por registro». */
    public function expireForRecord(int $recordId): int
    {
        return $this->expiredQuery()->where('id_cr_estudiante', $recordId)
            ->update(['estado' => RectificationRequest::EXPIRED]);
    }

    /** Marca una solicitud si ya venció. Devuelve si cambió. */
    public function expireOne(int $requestId): bool
    {
        return $this->expiredQuery()->where('id_solicitud_rect', $requestId)
            ->update(['estado' => RectificationRequest::EXPIRED]) === 1;
    }

    private function expiredQuery()
    {
        return RectificationRequest::query()
            ->where('estado', RectificationRequest::PENDING)
            ->where('vence_en', '<=', Carbon::now());
    }
}
