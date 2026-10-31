<?php

namespace App\Services\Exams;

use App\Exceptions\Exams\ExamStateException;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/** Regla compartida para impedir nuevos ingresos o situaciones de un estudiante retirado. */
class ExamExpulsionService
{
    /** Llamar dentro de la transacción que bloquea el examen, antes de escribir el ingreso (incluida HU-037). */
    public function assertCanEnter(int $examId, int $studentId): void
    {
        $timestamp = DB::table('examen_estudiante')->where('id_examen', $examId)
            ->where('id_estudiante', $studentId)->value('expulsado_en');
        $this->assertNotExpelled($timestamp);
    }

    public function assertNotExpelled(?string $timestamp): void
    {
        if ($timestamp !== null) {
            $time = Carbon::parse($timestamp)->setTimezone(config('sciem.zona_horaria', 'America/La_Paz'));
            throw new ExamStateException('El estudiante fue expulsado de este examen a las ' . $time->format('H:i'));
        }
    }
}