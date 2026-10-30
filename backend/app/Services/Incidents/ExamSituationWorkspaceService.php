<?php

namespace App\Services\Incidents;

use App\Models\Exam;
use App\Models\Student;
use App\Models\User;
use App\Support\Database\AccentInsensitiveSearch;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** Consultas de la pantalla independiente; no registra asistencia ni cambia el estado del examen. */
class ExamSituationWorkspaceService
{
    private ExamSituationAccessService $access;
    private ExamSituationSummaryService $summaries;

    public function __construct(ExamSituationAccessService $access, ExamSituationSummaryService $summaries)
    {
        $this->access = $access;
        $this->summaries = $summaries;
    }

    public function exams(array $filters, User $actor): LengthAwarePaginator
    {
        return $this->access->visibleExams($actor)
            ->where('estado', $filters['estado'] ?? Exam::EN_CURSO)
            ->orderByDesc('fecha')->orderByDesc('id_examen')
            ->paginate(20, ['examen.*'], 'page', (int) ($filters['page'] ?? 1));
    }

    public function context(int $examId, array $filters, User $actor): array
    {
        $exam = Exam::query()->with('classrooms')->findOrFail($examId);
        $scope = $this->access->roomScope($exam, $actor);

        if (! in_array($exam->estado, [Exam::EN_CURSO, Exam::FINALIZADO], true)) {
            throw new HttpException(409, 'El examen no está en curso ni finalizado.');
        }

        $roomId = isset($filters['id_ambiente']) ? (int) $filters['id_ambiente'] : $scope;

        if ($roomId !== null) {
            $this->access->assertRoomAllowed($exam, $roomId, $scope);
        }

        $query = Student::query()
            ->join('examen_estudiante as ee', 'ee.id_estudiante', '=', 'estudiante.id_estudiante')
            ->join('ambiente as a', 'a.id_ambiente', '=', 'ee.id_ambiente')
            ->where('ee.id_examen', $examId)
            ->whereIn('ee.estado_ingreso', ['INGRESO', 'CON_RETRASO'])
            ->select([
                'estudiante.id_estudiante', 'estudiante.cod_sis', 'estudiante.nombre',
                'estudiante.apellido_paterno', 'estudiante.apellido_materno',
                'ee.estado_ingreso', 'ee.id_ambiente', 'a.nro_aula',
            ]);

        if ($roomId !== null) {
            $query->where('ee.id_ambiente', $roomId);
        }

        if (isset($filters['id_estudiante'])) {
            $query->where('estudiante.id_estudiante', (int) $filters['id_estudiante']);
        }

        if (($filters['q'] ?? '') !== '') {
            $pattern = AccentInsensitiveSearch::pattern(AccentInsensitiveSearch::normalize($filters['q']));
            $query->where(function ($search) use ($pattern) {
                $name = "concat_ws(' ', estudiante.nombre, estudiante.apellido_paterno, estudiante.apellido_materno)";
                $search->whereRaw(AccentInsensitiveSearch::condition($name), [$pattern])
                    ->orWhereRaw(AccentInsensitiveSearch::condition('estudiante.cod_sis'), [$pattern]);
            });
        }

        $page = $query->orderBy('estudiante.apellido_paterno')->orderBy('estudiante.apellido_materno')
            ->orderBy('estudiante.nombre')->orderBy('estudiante.id_estudiante')
            ->paginate(20, ['*'], 'page', (int) ($filters['page'] ?? 1));
        $ids = $page->getCollection()->pluck('id_estudiante')->all();

        return [
            'exam' => $exam,
            'room_scope' => $scope,
            'page' => $page,
            'summaries' => $this->summaries->forStudents($examId, $ids, $actor),
            'server_time' => now('UTC')->toIso8601String(),
        ];
    }
}