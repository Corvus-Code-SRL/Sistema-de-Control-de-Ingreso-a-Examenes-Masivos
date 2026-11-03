<?php

namespace App\Services\Incidents;

use App\Models\Exam;
use App\Models\ExamSituation;
use App\Models\User;
use App\Support\ExamSituationType;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Aporta las marcas de situación a las filas que consulta la vista En curso. */
class ExamSituationSummaryService
{
    private ExamSituationAccessService $access;

    public function __construct(ExamSituationAccessService $access)
    {
        $this->access = $access;
    }

    /** Devuelve un mapa por id_estudiante, limitado a estudiantes visibles para el actor. */
    public function forStudents(int $examId, array $studentIds, User $actor): array
    {
        $exam = Exam::query()->findOrFail($examId);
        $roomScope = $this->access->roomScope($exam, $actor);

        if (count($studentIds) > 200) {
            throw new InvalidArgumentException('Consulte el resumen por páginas de hasta 200 estudiantes.');
        }

        $studentIds = array_values(array_unique(array_map('intval', $studentIds)));
        $attendance = DB::table('examen_estudiante')
            ->where('id_examen', $examId)
            ->whereIn('id_estudiante', $studentIds);

        if ($roomScope !== null) {
            $attendance->where('id_ambiente', $roomScope);
        }

        $visibleIds = $attendance->pluck('id_estudiante')->map(fn ($id) => (int) $id)->all();
        $summaries = [];

        foreach ($visibleIds as $studentId) {
            $summaries[$studentId] = ['identidad_observada' => false, 'ultimo_cambio_ambiente' => null];
        }

        if ($visibleIds === []) {
            return $summaries;
        }

        $observedIds = DB::table('situacion_examen')
            ->where('id_examen', $examId)
            ->whereIn('id_estudiante', $visibleIds)
            ->where('tipo', ExamSituationType::IDENTIFICATION)
            ->distinct()
            ->pluck('id_estudiante');

        foreach ($observedIds as $studentId) {
            $summaries[(int) $studentId]['identidad_observada'] = true;
        }

        // DISTINCT ON conserva únicamente el cambio más reciente de cada estudiante en PostgreSQL.
        $changes = ExamSituation::query()
            ->selectRaw('DISTINCT ON (id_estudiante) situacion_examen.*')
            ->with(['originRoom', 'destinationRoom'])
            ->where('id_examen', $examId)
            ->whereIn('id_estudiante', $visibleIds)
            ->where('tipo', ExamSituationType::ROOM_CHANGE)
            ->orderBy('id_estudiante')
            ->orderByDesc('registrado_en')
            ->orderByDesc('id_situacion')
            ->get();

        foreach ($changes as $change) {
            $summaries[(int) $change->id_estudiante]['ultimo_cambio_ambiente'] = [
                'id_situacion' => (int) $change->id_situacion,
                'registrado_en' => $change->registrado_en->copy()->utc()->toIso8601String(),
                'ambiente_origen' => [
                    'id_ambiente' => (int) $change->id_ambiente_origen,
                    'nro_aula' => $change->originRoom->nro_aula,
                ],
                'ambiente_destino' => [
                    'id_ambiente' => (int) $change->id_ambiente_destino,
                    'nro_aula' => $change->destinationRoom->nro_aula,
                ],
            ];
        }

        return $summaries;
    }
}