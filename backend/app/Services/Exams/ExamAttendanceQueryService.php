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
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\DB;

/** Catálogo y detalle de asistencia de exámenes académicos. */
class ExamAttendanceQueryService
{
    private const CANDIDATE_STATES = [Exam::EN_CURSO, Exam::EN_INGRESO];
    private const ACCEPTED_INVITATION = 'ACEPTADA';
    private const STATUS_TOTALS = [
        'PENDIENTE' => 'pendientes',
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

    public function show(int $examId, User $actor): array
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

        $students = $this->attendanceRows($examId, $rooms->all(), $isTeacher);

        if ($classroom !== null) {
            $roomId = (int) $classroom->id_ambiente;
            $students = $students->whereStrict('id_ambiente', $roomId)->values();
            $rooms = $rooms->whereStrict('id_ambiente', $roomId)->values();
        }

        $totals = array_fill_keys(array_values(self::STATUS_TOTALS), 0);
        foreach ($students as $student) {
            $student->estado = $this->attendanceStatus($student, $exam->estado);
            $totals[self::STATUS_TOTALS[$student->estado]]++;
        }

        return [
            'exam' => $exam,
            'classroom' => $classroom,
            'rooms' => $rooms,
            'students' => $students,
            'can_view_ci' => $isTeacher,
            // HU-028 aún no registra la carga de una nómina de habilitados para el examen.
            'totals' => ['total' => $students->count(), 'no_habilitados' => 0] + $totals,
        ];
    }

    private function attendanceRows(int $examId, array $rooms, bool $canViewCi): BaseCollection
    {
        // Se usa el grupo canónico de la nómina, con el respaldo multicarrera del grupo.
        $careerId = 'COALESCE(ge.id_carrera, CASE WHEN '
            . '(SELECT count(*) FROM grupo_oferta WHERE grupo_oferta.id_grupo = g.id_grupo) <= 1 '
            . 'THEN g.id_carrera END)';

        $roster = DB::query()
            ->fromSub($this->participants->expected($examId), 'nomina')
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
                'nomina.id_estudiante', 'nomina.cod_sis',
                'nomina.nombre', 'nomina.apellido_paterno', 'nomina.apellido_materno',
                'c.id_carrera', 'c.nombre as carrera',
                'ee.id_estudiante as id_registro_asistencia', 'ee.estado_ingreso', 'ee.hora_ingreso',
                'ee.id_ambiente', 'ee.expulsado_en',
            ])
            ->when($canViewCi, static fn ($query) => $query->addSelect('nomina.ci'))
            ->orderBy('nomina.id_estudiante')
            ->get();

        $assignedRooms = EntryControlSnapshotService::assignRooms($roster->pluck('id_estudiante')->all(), $rooms);

        return $roster->each(static function (object $student) use ($assignedRooms): void {
            $student->id_ambiente = $student->id_ambiente === null
                ? ($assignedRooms[$student->id_estudiante]['id_ambiente'] ?? null)
                : (int) $student->id_ambiente;
        });
    }

    private function attendanceStatus(object $student, string $examState): string
    {
        return match (true) {
            $student->expulsado_en !== null => 'EXPULSADO',
            $student->estado_ingreso === StudentExam::INGRESO => 'A_TIEMPO',
            $student->estado_ingreso === StudentExam::CON_RETRASO => StudentExam::CON_RETRASO,
            $student->id_registro_asistencia === null,
            $student->estado_ingreso === StudentExam::NO_INGRESO => $examState === Exam::EN_INGRESO
                ? 'PENDIENTE'
                : 'AUSENTE',
            default => 'SIN_CLASIFICAR',
        };
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
