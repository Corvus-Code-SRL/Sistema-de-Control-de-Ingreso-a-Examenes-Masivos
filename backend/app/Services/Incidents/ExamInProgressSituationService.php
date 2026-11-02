<?php

namespace App\Services\Incidents;

use App\Models\Exam;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Conecta los resúmenes y la revisión del historial con la consulta de HU-038-A. */
class ExamInProgressSituationService
{
    public function __construct(
        private ExamSituationAccessService $access,
        private ExamSituationSummaryService $summaries
    ) {
    }

    public function forExam(Exam $exam, array $studentIds, User $actor): array
    {
        // Las situaciones académicas no se mezclan con exámenes de admisión.
        if ($exam->id_carrera === null) {
            return ['enabled' => false, 'revision' => 0, 'students' => []];
        }

        $roomScope = $this->access->roomScope($exam, $actor);
        $examId = (int) $exam->id_examen;
        $history = DB::table('situacion_examen')->where('id_examen', $examId);

        if ($roomScope !== null) {
            $history->where(function ($query) use ($roomScope): void {
                $query->where('id_ambiente_origen', $roomScope)->orWhere('id_ambiente_destino', $roomScope);
            });
        }

        // El historial es de altas: contar detecta incluso transacciones que confirman fuera del orden de sus ids.
        // Incluye traslados salientes, aunque el alumno ya no aparezca en la nómina visible del auxiliar.
        $revision = $history->count();
        $students = [];

        // La nómina de En curso puede superar 200 alumnos. Se consulta por lotes, nunca por fila.
        foreach (array_chunk(array_values(array_unique($studentIds)), 200) as $ids) {
            $students += $this->summaries->forStudents($examId, $ids, $actor);
        }

        return ['enabled' => true, 'revision' => $revision, 'students' => $students];
    }
}