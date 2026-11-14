<?php

namespace App\Services\Exams;

use App\Models\Classroom;
use App\Models\Exam;
use App\Models\Role;
use App\Models\User;
use App\Services\EntryControl\EntryControlSnapshotService;
use App\Services\Security\UserRoleService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;

/** Catálogo de exámenes disponibles para consultar la asistencia. */
class ExamAttendanceQueryService
{
    private const CANDIDATE_STATES = [Exam::EN_CURSO, Exam::EN_INGRESO];

    public function __construct(
        private UserRoleService $roles,
        private EntryControlSnapshotService $snapshots
    ) {
    }

    /** @return Collection<int, Exam> */
    public function listAvailable(User $actor): Collection
    {
        $actorId = $actor->id_usuario;
        $isTeacher = $this->roles->isTeacher($actorId);

        if (! $isTeacher && ! $this->roles->hasAnyActiveRole($actorId, [Role::AUXILIAR])) {
            throw new AuthorizationException();
        }

        $query = Exam::query()
            ->select(['id_examen', 'nombre_examen', 'id_materia', 'fecha', 'hora_inicio', 'hora_fin', 'estado'])
            ->whereIn('estado', self::CANDIDATE_STATES)
            ->whereDoesntHave('admissionExam')
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
            ->orderBy('id_examen');

        if ($isTeacher) {
            $query->where('id_usuario_docente', $actorId);
        } else {
            $assignedToActor = static fn ($query) => $query->where('id_usuario', $actorId);

            // Como en la vista En curso, el acceso al examen propio exige ejercer la docencia.
            $query->where('id_usuario_docente', '<>', $actorId)
                ->whereHas('assistants', $assignedToActor)
                ->with([
                    'assistants' => static fn ($query) => $assignedToActor($query)
                        ->select(['id_examen', 'id_ambiente']),
                    'classrooms:id_ambiente,nro_aula',
                ]);
        }

        $exams = $query->get()->filter(function (Exam $exam) use ($isTeacher): bool {
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
