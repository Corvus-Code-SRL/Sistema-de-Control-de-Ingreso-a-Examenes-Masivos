<?php

namespace App\Services\EntryControl;

use App\Models\Exam;
use App\Models\StudentExam;
use App\Services\Exams\ExamTimingService;
use App\Support\ClockTime;
use Carbon\Carbon;

/**
 * Advertencia del ingreso con retraso (HU-037): el estudiante ya ingresó a otro examen que sigue
 * abierto (EN_INGRESO o EN_CURSO) y cuya hora de fin programada no pasó. No bloquea: quien
 * controla decide. Un examen anterior que solo espera su cierre automático no cuenta, así dos
 * exámenes seguidos (8:00–10:00 y 10:00–12:00) nunca se advierten.
 */
class LateEntryOtherExamService
{
    private ExamTimingService $timing;

    public function __construct(ExamTimingService $timing)
    {
        $this->timing = $timing;
    }

    /**
     * @return array{id_examen: int, nombre_examen: string, materia: string|null, docente: string|null,
     *     hora_ingreso: string|null, nro_aula: string|null}|null
     */
    public function forStudent(Exam $exam, int $studentId): ?array
    {
        return $this->forStudents($exam, [$studentId])[$studentId] ?? null;
    }

    /**
     * Una sola consulta para todos los estudiantes (más la carga de materia y docente).
     *
     * @param array<int, int> $studentIds
     * @return array<int, array{id_examen: int, nombre_examen: string, materia: string|null,
     *     docente: string|null, hora_ingreso: string|null, nro_aula: string|null}>
     */
    public function forStudents(Exam $exam, array $studentIds): array
    {
        if ($studentIds === []) {
            return [];
        }

        $now = Carbon::now(config('sciem.zona_horaria'));
        $entries = Exam::query()
            ->join('examen_estudiante as ee', 'ee.id_examen', '=', 'examen.id_examen')
            ->leftJoin('ambiente as a', 'a.id_ambiente', '=', 'ee.id_ambiente')
            ->with(['subject', 'teacher'])
            ->whereIn('ee.id_estudiante', array_values(array_unique($studentIds)))
            ->where('examen.id_examen', '<>', $exam->id_examen)
            ->whereIn('examen.estado', [Exam::EN_INGRESO, Exam::EN_CURSO])
            ->whereIn('ee.estado_ingreso', [StudentExam::INGRESO, StudentExam::CON_RETRASO])
            ->orderBy('ee.hora_ingreso')
            ->get([
                'examen.*',
                'ee.id_estudiante as ingreso_id_estudiante',
                'ee.hora_ingreso as ingreso_hora',
                'a.nro_aula as ingreso_nro_aula',
            ]);

        $warnings = [];

        foreach ($entries as $entry) {
            $studentId = (int) $entry->ingreso_id_estudiante;

            if (isset($warnings[$studentId]) || $this->timing->hasEnded($entry, $now)) {
                continue;
            }

            $warnings[$studentId] = [
                'id_examen' => (int) $entry->id_examen,
                'nombre_examen' => $entry->nombre_examen,
                'materia' => $entry->subject?->nombre,
                'docente' => $entry->teacher?->nombre_completo,
                'hora_ingreso' => $entry->ingreso_hora === null ? null : ClockTime::fromTime($entry->ingreso_hora),
                'nro_aula' => $entry->ingreso_nro_aula,
            ];
        }

        return $warnings;
    }
}
