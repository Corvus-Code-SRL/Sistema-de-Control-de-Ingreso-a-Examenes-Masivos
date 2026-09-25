<?php

namespace App\Services\Exams;

use App\Models\Exam;
use Illuminate\Support\Facades\DB;

/**
 * Decide si la nómina de un grupo puede cambiar.
 *
 * Mientras un examen del grupo esté en ingreso o en curso, quién debe entrar ya está
 * en juego en la puerta: la nómina queda congelada. Con el examen PROGRAMADO puede
 * cambiar libremente, porque los participantes se derivan de grupo_estudiante.
 */
class ExamRosterLockService
{
    /** Estados de examen que congelan la nómina de sus grupos. */
    private const LOCKING_STATES = [Exam::EN_INGRESO, Exam::EN_CURSO];

    public function isLocked(int $groupId): bool
    {
        return DB::table('grupo_examen')
            ->join('examen', 'examen.id_examen', '=', 'grupo_examen.id_examen')
            ->where('grupo_examen.id_grupo', $groupId)
            ->whereIn('examen.estado', self::LOCKING_STATES)
            ->exists();
    }
}
