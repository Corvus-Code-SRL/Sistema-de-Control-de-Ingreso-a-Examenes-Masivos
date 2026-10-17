<?php

namespace App\Services\EntryControl;

use App\Models\EntryAttempt;
use App\Models\Exam;
use App\Models\User;
use Illuminate\Support\Collection;

/** Consulta rechazos recientes y el reporte asociado sin depender de la caché de ingresos. */
class EntryAttemptQueryService
{
    private EntryAccessService $access;

    public function __construct(EntryAccessService $access)
    {
        $this->access = $access;
    }

    public function recent(int $examId, int $roomId, User $actor): Collection
    {
        $exam = Exam::query()->findOrFail($examId);
        $this->access->assertCanControlRoom($exam, $roomId, $actor);
        $room = $exam->classrooms->firstWhere('id_ambiente', $roomId);

        return EntryAttempt::query()
            ->with(['student', 'studentReport:id_reporte_est,id_intento'])
            ->where('id_examen', $examId)
            ->where('id_ambiente', $roomId)
            ->orderByDesc('registrado_en')
            ->orderByDesc('id_intento')
            ->limit(20)
            ->get()
            ->map(fn (EntryAttempt $attempt) => ['attempt' => $attempt, 'room' => $room]);
    }
}
