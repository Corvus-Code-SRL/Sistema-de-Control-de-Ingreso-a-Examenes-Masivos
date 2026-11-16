<?php

namespace App\Services\Exams;

use App\Models\ExamInvitation;
use Carbon\Carbon;

/**
 * Deja sin efecto las invitaciones que ya no se pueden responder (HU-026).
 *
 * Una invitación PENDIENTE solo tiene sentido mientras el examen está PROGRAMADO. Cuando el examen sale de
 * ese estado (abre el control de ingreso, se cancela o termina) las pendientes pasan a SIN_EFECTO, con la
 * fecha en que ocurrió. Las aceptadas y rechazadas no se tocan.
 *
 * No abre transacción: lo llama el ciclo de vida del examen dentro de la que ya tiene abierta, con la fila del
 * examen bloqueada. Repetirlo no cambia nada.
 */
class ExamInvitationExpiryService
{
    /** @return int Cantidad de invitaciones que pasaron a SIN_EFECTO. */
    public function voidPendingForExam(int $examId, ?Carbon $at = null): int
    {
        return ExamInvitation::query()
            ->where('id_examen', $examId)
            ->where('estado', ExamInvitation::PENDIENTE)
            ->update([
                'estado' => ExamInvitation::SIN_EFECTO,
                'fecha_sin_efecto' => $at ?? Carbon::now(),
            ]);
    }
}
