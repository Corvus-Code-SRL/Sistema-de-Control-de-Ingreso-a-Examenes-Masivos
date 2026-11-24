<?php

namespace App\Services\Exams\Distribution;

use App\Models\Exam;
use App\Services\Exams\ExamPreparationWindow;
use App\Services\Exams\ExamTimingService;
use Carbon\Carbon;

/**
 * Ventana para editar la distribución (HU-031). Cierra a la hora de apertura del control aunque el
 * job de apertura aún no haya corrido o haya fallado, porque el examen sigue PROGRAMADO con el control abierto.
 */
class RoomDistributionWindow
{
    public function __construct(private ExamPreparationWindow $preparation, private ExamTimingService $timing)
    {
    }

    public function isOpen(Exam $exam): bool
    {
        return $this->preparation->isOpen($exam)
            && $exam->distribucion_suspendida_en === null
            && Carbon::now(config('sciem.zona_horaria'))->lessThan($this->timing->opensAt($exam));
    }
}
