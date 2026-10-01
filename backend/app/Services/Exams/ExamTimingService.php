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
            'Y-m-d H:i',
            $exam->fecha->toDateString().' '.substr($exam->hora_inicio, 0, 5),
            config('sciem.zona_horaria')
        );
    }

    public function endsAt(Exam $exam): Carbon
    {
        return $this->startsAt($exam)->addMinutes((int) $exam->duracion);
    }

    public function finishesAutomaticallyAt(Exam $exam): Carbon
    {
        return $this->endsAt($exam)->addHours(2);
    }

    public function hasEnded(Exam $exam, ?Carbon $now = null): bool
    {
        $now = $now ?? Carbon::now(config('sciem.zona_horaria'));

        return $now->greaterThanOrEqualTo($this->endsAt($exam));
    }
}
