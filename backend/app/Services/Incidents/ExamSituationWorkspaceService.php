<?php

namespace App\Services\Incidents;

use App\Models\Exam;
use App\Models\Student;
use App\Models\User;
use App\Support\Database\AccentInsensitiveSearch;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Consultas de la pantalla independiente; no registra asistencia ni cambia el estado del examen.
 *
 * `$scope` permite que otra historia (HU-042) reutilice la misma consulta con sus propios estados,
 * su propia función de acceso y sin estudiantes retirados. Claves: states, feature, exclude_expelled,
 * state_message y with_summaries (false evita consultar cualquier dato de situaciones).
 */
class ExamSituationWorkspaceService
{
    private ExamSituationAccessService $access;
    private ExamSituationSummaryService $summaries;

    public function __construct(ExamSituationAccessService $access, ExamSituationSummaryService $summaries)
    {
        $this->access = $access;
        $this->summaries = $summaries;
    }

    public function exams(array $filters, User $actor, array $scope = []): LengthAwarePaginator
    {
        $states = isset($filters['estado'])
            ? [$filters['estado']]
            : ($scope['states'] ?? ExamSituationAccessService::REGISTRABLE_STATES);

        return $this->accessFor($scope)->visibleExams($actor)
            ->whereIn('estado', $states)
            ->orderByDesc('fecha')->orderByDesc('id_examen')
            ->paginate(20, ['examen.*'], 'page', (int) ($filters['page'] ?? 1));
    }

    public function context(int $examId, array $filters, User $actor, array $options = []): array
    {
        $access = $this->accessFor($options);
        $exam = Exam::query()->with('classrooms')->findOrFail($examId);
        $scope = $access->roomScope($exam, $actor);

        if (isset($options['states'])) {
            $access->assertInStates($exam, $options['states'], $options['state_message']);
        } else {
            $access->assertRegistrable($exam);
        }

        $roomId = isset($filters['id_ambiente']) ? (int) $filters['id_ambiente'] : $scope;

        if ($roomId !== null) {
            $access->assertRoomAllowed($exam, $roomId, $scope);
        }

        $query = Student::query()
            ->join('examen_estudiante as ee', 'ee.id_estudiante', '=', 'estudiante.id_estudiante')
            ->join('ambiente as a', 'a.id_ambiente', '=', 'ee.id_ambiente')
            ->where('ee.id_examen', $examId)
            ->whereIn('ee.estado_ingreso', ['INGRESO', 'CON_RETRASO'])
            ->whereNull('ee.expulsado_en')
            ->select([
                'estudiante.id_estudiante', 'estudiante.cod_sis', 'estudiante.nombre',
                'estudiante.apellido_paterno', 'estudiante.apellido_materno',
                'ee.estado_ingreso', 'ee.id_ambiente', 'a.nro_aula',
            ]);

        if ($roomId !== null) {
            $query->where('ee.id_ambiente', $roomId);
        }

        if ($options['exclude_expelled'] ?? false) {
            $query->whereNull('ee.expulsado_en');
        }

        if (isset($filters['id_estudiante'])) {
            $query->where('estudiante.id_estudiante', (int) $filters['id_estudiante']);
        }

        if (($filters['q'] ?? '') !== '') {
            $pattern = AccentInsensitiveSearch::pattern(AccentInsensitiveSearch::normalize($filters['q']));
            $query->where(function ($search) use ($pattern) {
                $name = "concat_ws(' ', estudiante.nombre, estudiante.apellido_paterno, estudiante.apellido_materno)";
                $search->whereRaw(AccentInsensitiveSearch::condition($name), [$pattern])
                    ->orWhereRaw(AccentInsensitiveSearch::condition('estudiante.cod_sis'), [$pattern])
                    ->orWhereRaw(AccentInsensitiveSearch::condition('estudiante.ci'), [$pattern]);
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
            'summaries' => ($options['with_summaries'] ?? true)
                ? $this->summaries->forStudents($examId, $ids, $actor)
                : [],
            'server_time' => now('UTC')->toIso8601String(),
        ];
    }

    private function accessFor(array $options): ExamSituationAccessService
    {
        return isset($options['feature']) ? $this->access->forFeature($options['feature']) : $this->access;
    }
}
