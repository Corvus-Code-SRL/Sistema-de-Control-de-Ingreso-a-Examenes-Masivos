<?php

namespace App\Services\Exams;

use App\Exceptions\Exams\ExamStateException;
use App\Models\Classroom;
use App\Models\Exam;
use App\Models\ExamInvitation;
use App\Models\Role;
use App\Models\StudentExam;
use App\Models\User;
use App\Services\EntryControl\EntryControlSnapshotService;
use App\Services\Security\UserRoleService;
use App\Support\Database\AccentInsensitiveSearch;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/** Catálogo y detalle de asistencia de exámenes académicos. */
class ExamAttendanceQueryService
{
    private const CANDIDATE_STATES = [Exam::EN_CURSO, Exam::EN_INGRESO];
    private const ACCEPTED_INVITATION = 'ACEPTADA';
    private const PER_PAGE = 20;
    private const STATUS_TOTALS = [
        'SIN_REGISTRO' => 'sin_registro',
        'AUSENTE' => 'ausentes',
        'A_TIEMPO' => 'a_tiempo',
        'CON_RETRASO' => 'con_retraso',
        'EXPULSADO' => 'expulsados',
        'SIN_CLASIFICAR' => 'sin_clasificar',
    ];

    public function __construct(
        private UserRoleService $roles,
        private EntryControlSnapshotService $snapshots,
        private ExamParticipantService $participants
    ) {
    }

    /** @return Collection<int, Exam> */
    public function listAvailable(User $actor): Collection
    {
        $isTeacher = $this->canConsultAllRooms($actor);
        $exams = $this->accessibleExams($actor, $isTeacher)
            ->select(['id_examen', 'nombre_examen', 'id_materia', 'fecha', 'hora_inicio', 'hora_fin', 'estado'])
            ->whereIn('estado', self::CANDIDATE_STATES)
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
            ->orderBy('id_examen')
            ->get();

        $exams = $exams->filter(function (Exam $exam) use ($isTeacher): bool {
            $classroom = $isTeacher ? null : $this->resolveAssistantClassroom($exam);

            if ((! $isTeacher && $classroom === null) || ! $this->isEligible($exam)) {
                return false;
            }

            $exam->setRelation('attendanceClassroom', $classroom);

            return true;
        })->values();

        return $exams->load([
            'subject:id_materia,nombre,codigo',
            'groups' => static fn ($query) => $query->select(['grupo.id_grupo', 'grupo.num_grupo'])
                ->orderBy('grupo.num_grupo')
                ->orderBy('grupo.id_grupo'),
        ]);
    }

    public function show(int $examId, User $actor, array $filters = []): array
    {
        $isTeacher = $this->canConsultAllRooms($actor);
        $exam = $this->accessibleExams($actor, $isTeacher)->find($examId);

        if ($exam === null) {
            throw new AuthorizationException();
        }

        $exam->loadMissing('classrooms:id_ambiente,nro_aula,capacidad');
        $classroom = $isTeacher ? null : $this->resolveAssistantClassroom($exam);

        if (! $isTeacher && $classroom === null) {
            throw new AuthorizationException();
        }

        if (! $this->isEligible($exam)) {
            throw new ExamStateException('El examen no tiene el ingreso abierto ni está en curso.');
        }

        $rooms = $exam->classrooms->sortBy('id_ambiente')->map(static fn (Classroom $room): array => [
            'id_ambiente' => (int) $room->id_ambiente,
            'nro_aula' => $room->nro_aula,
            'capacidad' => (int) $room->capacidad,
        ])->values();

        $scope = DB::query()->fromSub($this->attendanceRows($examId, $rooms->all()), 'asistencia');

        if ($classroom !== null) {
            $roomId = (int) $classroom->id_ambiente;
            $scope->where('id_ambiente', $roomId);
            $rooms = $rooms->whereStrict('id_ambiente', $roomId)->values();
        }

        $totals = $this->attendanceTotals($scope);
        $careers = (clone $scope)->whereNotNull('id_carrera')->distinct()
            ->orderBy('id_carrera')->get(['id_carrera', 'carrera']);

        $rows = $this->applyFilters($scope, $filters, $classroom === null && $rooms->count() > 1);
        $total = $rows->count();
        $lastPage = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min((int) ($filters['page'] ?? 1), $lastPage);

        return [
            'exam' => $exam,
            'classroom' => $classroom,
            'rooms' => $rooms,
            'careers' => $careers,
            'students' => $rows->orderBy('id_estudiante')->forPage($page, self::PER_PAGE)->get(),
            'totals' => $totals,
            'pagination' => [
                'current_page' => $page, 'last_page' => $lastPage, 'per_page' => self::PER_PAGE, 'total' => $total,
            ],
        ];
    }

    private function attendanceRows(int $examId, array $rooms): QueryBuilder
    {
        // Se usa el grupo canónico de la nómina, con el respaldo multicarrera del grupo.
        $careerId = 'COALESCE(ge.id_carrera, CASE WHEN NOT EXISTS '
            . '(SELECT 1 FROM grupo_oferta go WHERE go.id_grupo = g.id_grupo AND go.id_carrera <> g.id_carrera) '
            . 'THEN g.id_carrera END)';
        $confirmed = 'ee.hora_ingreso IS NOT NULL AND ee.estado_ingreso::text IN (?, ?)';
        $roster = $this->participants->expected($examId);

        if ($rooms !== []) {
            $roster->selectRaw('row_number() OVER (ORDER BY estudiante.id_estudiante) AS posicion');
        }

        $rows = DB::query()->fromSub($roster, 'nomina')
            ->join('grupo as g', 'g.id_grupo', '=', 'nomina.id_grupo')
            ->join('grupo_estudiante as ge', static function ($join): void {
                $join->on('ge.id_grupo', '=', 'nomina.id_grupo')
                    ->on('ge.id_estudiante', '=', 'nomina.id_estudiante');
            })
            ->leftJoin('carrera as c', 'c.id_carrera', '=', DB::raw($careerId))
            ->leftJoin('examen_estudiante as ee', static function ($join) use ($examId): void {
                $join->on('ee.id_estudiante', '=', 'nomina.id_estudiante')
                    ->where('ee.id_examen', $examId);
            })
            ->select([
                'nomina.id_estudiante', 'nomina.cod_sis', 'nomina.ci',
                'nomina.nombre', 'nomina.apellido_paterno', 'nomina.apellido_materno',
                'c.id_carrera', 'c.nombre as carrera',
                'ee.expulsado_en',
            ])
            ->selectRaw("CASE WHEN $confirmed THEN 1 ELSE 0 END AS registrado", [
                StudentExam::INGRESO, StudentExam::CON_RETRASO,
            ])
            ->selectRaw("CASE WHEN ($confirmed) OR ee.expulsado_en IS NOT NULL THEN ee.hora_ingreso END AS hora_ingreso", [
                StudentExam::INGRESO, StudentExam::CON_RETRASO,
            ])
            // HU-032 aún no expone un cierre confirmado e independiente del horario del examen.
            ->selectRaw("CASE
                WHEN ee.expulsado_en IS NOT NULL THEN 'EXPULSADO'
                WHEN ee.hora_ingreso IS NOT NULL AND ee.estado_ingreso::text = ? THEN 'A_TIEMPO'
                WHEN ee.hora_ingreso IS NOT NULL AND ee.estado_ingreso::text = ? THEN 'CON_RETRASO'
                WHEN ee.id_estudiante IS NULL THEN 'SIN_REGISTRO'
                WHEN ee.estado_ingreso::text = ? AND ee.hora_ingreso IS NULL THEN 'SIN_REGISTRO'
                ELSE 'SIN_CLASIFICAR'
            END AS estado", [StudentExam::INGRESO, StudentExam::CON_RETRASO, StudentExam::NO_INGRESO]);

        if ($rooms === []) {
            return $rows->addSelect('ee.id_ambiente');
        }

        // Igual que assignRooms: posiciones por ID de estudiante y tramos acumulados por ID de aula.
        $bounds = [];
        $position = 0;
        foreach ($rooms as $room) {
            $end = $position + $room['capacidad'];
            $bounds[] = [
                'id_ambiente' => $room['id_ambiente'],
                'desde' => $position,
                'hasta' => $end,
            ];
            $position = $end;
        }
        $roomRanges = DB::query()->select('*')->fromRaw(
            'jsonb_to_recordset(CAST(? AS jsonb)) AS rango(id_ambiente integer, desde bigint, hasta bigint)',
            [json_encode($bounds, JSON_THROW_ON_ERROR)]
        );

        return $rows->leftJoinSub($roomRanges, 'aula', static function ($join): void {
            $join->on('nomina.posicion', '>', 'aula.desde')->on('nomina.posicion', '<=', 'aula.hasta');
        })->selectRaw('COALESCE(ee.id_ambiente, aula.id_ambiente) AS id_ambiente');
    }

    private function attendanceTotals(QueryBuilder $scope): array
    {
        // HU-028 aún no registra una lista de habilitados comprobable para este examen.
        $totals = ['total' => 0, 'registrados' => 0, 'sin_confirmar' => 0, 'no_habilitados' => 0]
            + array_fill_keys(array_values(self::STATUS_TOTALS), 0);
        $counts = (clone $scope)->select('estado', 'registrado')->selectRaw('count(*) AS cantidad')
            ->groupBy('estado', 'registrado')->get();
        foreach ($counts as $count) {
            $total = (int) $count->cantidad;
            $totals['total'] += $total;
            $totals[$count->registrado ? 'registrados' : 'sin_confirmar'] += $total;
            $totals[self::STATUS_TOTALS[$count->estado]] += $total;
        }

        return $totals;
    }

    private function applyFilters(QueryBuilder $scope, array $filters, bool $canFilterRoom): QueryBuilder
    {
        $rows = (clone $scope)->where('registrado', ($filters['vista'] ?? 'registrados') === 'sin_registro' ? 0 : 1);
        foreach (['carrera' => 'id_carrera', 'estado' => 'estado', 'ambiente' => 'id_ambiente'] as $param => $column) {
            if (isset($filters[$param]) && ($param !== 'ambiente' || $canFilterRoom)) {
                $rows->where($column, $filters[$param]);
            }
        }

        $identity = "concat_ws(' ', nombre, apellido_paterno, apellido_materno, cod_sis, ci)";
        $terms = preg_split('/\s+/u', AccentInsensitiveSearch::normalize($filters['q'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
        foreach ($terms ?: [] as $term) {
            $rows->whereRaw(AccentInsensitiveSearch::condition($identity), [AccentInsensitiveSearch::pattern($term)]);
        }

        return $rows;
    }

    private function canConsultAllRooms(User $actor): bool
    {
        $attendanceRoles = $this->roles->rolesFor('exam_attendance');
        $teacherRoles = array_intersect($attendanceRoles, $this->roles->rolesFor('teaching'));

        if ($this->roles->hasAnyActiveRole($actor->id_usuario, $teacherRoles)) {
            return true;
        }

        if (! in_array(Role::AUXILIAR, $attendanceRoles, true)
            || ! $this->roles->hasAnyActiveRole($actor->id_usuario, [Role::AUXILIAR])) {
            throw new AuthorizationException();
        }

        return false;
    }

    private function accessibleExams(User $actor, bool $isTeacher): Builder
    {
        $actorId = $actor->id_usuario;
        $query = Exam::query()->whereDoesntHave('admissionExam');

        if ($isTeacher) {
            $lastAnswer = ExamInvitation::query()
                ->select('estado')
                ->whereColumn('id_examen', 'examen.id_examen')
                ->whereColumn('id_docente_creador', 'examen.id_usuario_docente')
                ->where('id_docente_invitado', $actorId)
                ->whereNotNull('fecha_respuesta')
                ->orderByDesc('fecha_respuesta')
                ->orderByDesc('id_invitacion')
                ->limit(1);

            return $query->where(static function (Builder $exams) use ($actorId, $lastAnswer): void {
                $exams->where('id_usuario_docente', $actorId)->orWhere($lastAnswer, '=', self::ACCEPTED_INVITATION);
            });
        }

        $assignedToActor = static fn ($query) => $query->where('id_usuario', $actorId);

        // Como en la vista En curso, el acceso al examen propio exige ejercer la docencia.
        return $query->where('id_usuario_docente', '<>', $actorId)
            ->whereHas('assistants', $assignedToActor)
            ->with([
                'assistants' => static fn ($query) => $assignedToActor($query)
                    ->select(['id_examen', 'id_ambiente']),
                'classrooms:id_ambiente,nro_aula,capacidad',
            ]);
    }

    private function isEligible(Exam $exam): bool
    {
        return match ($exam->estado) {
            Exam::EN_CURSO => true,
            Exam::EN_INGRESO => $this->snapshots->ready((int) $exam->id_examen),
            default => false,
        };
    }

    private function resolveAssistantClassroom(Exam $exam): ?Classroom
    {
        $assignment = $exam->assistants->first();

        if ($assignment === null) {
            return null;
        }

        $rooms = $exam->classrooms->map(static fn (Classroom $classroom): array => [
            'id_ambiente' => (int) $classroom->id_ambiente,
        ])->all();
        $roomId = EntryControlSnapshotService::controlRoomOf($assignment->id_ambiente, $rooms);

        return $roomId === null ? null : $exam->classrooms->find($roomId);
    }
}
