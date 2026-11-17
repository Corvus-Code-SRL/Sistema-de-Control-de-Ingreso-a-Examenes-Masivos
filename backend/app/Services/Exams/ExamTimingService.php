<?php

namespace App\Services\Exams;

use App\Models\Exam;
use Carbon\Carbon;

/** Calcula los hitos temporales de un examen en la zona horaria del campus. */
class ExamTimingService
{
    public function startsAt(Exam $exam): Carbon
    {
        return Carbon::createFromFormat(
            '!Y-m-d H:i',
            $exam->fecha->toDateString().' '.substr($exam->hora_inicio, 0, 5),
            config('sciem.zona_horaria')
        );
    }

    public function opensAt(Exam $exam): Carbon
    {
        return $this->startsAt($exam)->subMinutes((int) $exam->minutos_apertura);
    }

    public function endsAt(Exam $exam): Carbon
    {
        return $this->startsAt($exam)->addMinutes((int) $exam->duracion);
    }

    public function resolutionLimit(Exam $exam): Carbon
    {
        return $this->endsAt($exam);
    }

    public function finishesAutomaticallyAt(Exam $exam): Carbon
    {
        // Los exámenes que quedaron sin iniciar conservan el plazo de recuperación.
        return $this->endsAt($exam)->addMinutes($exam->estado === Exam::EN_CURSO ? 10 : 120);
    }

    public function hasEnded(Exam $exam, ?Carbon $now = null): bool
    {
        $now = $now ?? Carbon::now(config('sciem.zona_horaria'));

        return $now->greaterThanOrEqualTo($this->endsAt($exam));
    }
}
