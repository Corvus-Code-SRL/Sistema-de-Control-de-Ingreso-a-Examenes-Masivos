<?php

namespace App\Services\Exams;

use App\Exceptions\Exams\ExamOwnershipException;
use App\Exceptions\Exams\ExamStateException;
use App\Models\Exam;
use App\Services\Academic\SubjectCatalogService;
use App\Support\RecordStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Vincula grupos válidos con un examen.
 *
 * Solo escribe el vínculo grupo-examen. Los participantes no se copian: se derivan de
 * grupo_estudiante (ver ExamParticipantService) y examen_estudiante solo registra
 * ingresos reales, así que la nómina puede cambiar mientras el examen esté programado.
 */
class ExamGroupService
{
    private SubjectCatalogService $subjectCatalog;

    public function __construct(SubjectCatalogService $subjectCatalog)
    {
        $this->subjectCatalog = $subjectCatalog;
    }

    /**
     * Solo el creador puede modificar grupos mientras el examen siga PROGRAMADO.
     */
    public function assignGroups(Exam $exam, array $groupIds): Exam
    {
        return DB::transaction(function () use ($exam, $groupIds) {
            $exam = Exam::query()
                ->whereKey($exam->id_examen)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertExamCanBeConfigured($exam);

            $groupIds = array_values(array_unique(array_map('intval', $groupIds)));
            $groups = $this->lockGroups($groupIds);

            $this->assertGroupsMatchExam($exam, $groups, $groupIds);

            $enrollments = $this->enrollments($groupIds);
            $this->assertEveryGroupHasRoster($groups, $enrollments);
            $this->assertStudentsAreNotRepeated($enrollments);

            /*
             * Cada docente reemplaza solo los grupos que él mismo había vinculado, nunca
             * el examen entero: si en el futuro varios docentes participan del mismo
             * examen (módulo assistants, aún sin construir), esto evita que uno pise la
             * lista del otro. Hoy assertExamCanBeConfigured ya limita quién puede llamar
             * este método al docente dueño del examen.
             */
            $teacherId = $this->subjectCatalog->teacherId();

            $previousOwnGroupIds = $exam->groups()
                ->where('id_usuario_docente', $teacherId)
                ->pluck('grupo.id_grupo')
                ->map(fn ($groupId) => (int) $groupId)
                ->all();

            $groupsToDetach = array_values(array_diff($previousOwnGroupIds, $groupIds));
            $groupsToAttach = array_values(array_diff($groupIds, $previousOwnGroupIds));

            $this->assertRemovedGroupsHaveNoEntries($exam, $groupsToDetach);

            if ($groupsToDetach !== []) {
                $exam->groups()->detach($groupsToDetach);
            }

            if ($groupsToAttach !== []) {
                $exam->groups()->attach($groupsToAttach);
            }

            return $exam->fresh([
                'examType',
                'subject',
                'career',
                'classrooms',
                'groups' => fn ($query) => $query->withStudentCount(),
            ]);
        });
    }

    private function assertExamCanBeConfigured(Exam $exam): void
    {
        if ((string) $exam->id_usuario_docente !== $this->subjectCatalog->teacherId()) {
            throw new ExamOwnershipException();
        }

        if ($exam->estado !== Exam::PROGRAMADO) {
            throw new ExamStateException(
                'Los grupos solo pueden modificarse mientras el examen está programado.'
            );
        }
    }

    private function lockGroups(array $groupIds): Collection
    {
        return DB::table('grupo')
            ->whereIn('id_grupo', $groupIds)
            ->orderBy('id_grupo')
            ->lockForUpdate()
            ->get();
    }

    private function assertGroupsMatchExam(Exam $exam, Collection $groups, array $groupIds): void
    {
        if ($groups->count() !== count($groupIds)) {
            throw ValidationException::withMessages([
                'grupos' => ['Uno o más grupos seleccionados no existen.'],
            ]);
        }

        $teacherId = $this->subjectCatalog->teacherId();
        $activePeriodId = $this->subjectCatalog->activePeriodId();

        foreach ($groups as $group) {
            $isValid = (string) $group->id_usuario_docente === $teacherId
                && (int) $group->id_periodo === $activePeriodId
                && $group->estado === RecordStatus::ACTIVE
                && (int) $group->id_carrera === (int) $exam->id_carrera
                && (int) $group->id_materia === (int) $exam->id_materia;

            if (! $isValid) {
                throw ValidationException::withMessages([
                    'grupos' => [
                        'Todos los grupos deben ser propios, activos, del periodo vigente '
                        . 'y del mismo par materia-carrera del examen.',
                    ],
                ]);
            }
        }
    }

    /**
     * Nómina cargada es tener filas en grupo_estudiante; su estado no se consulta.
     */
    private function enrollments(array $groupIds): Collection
    {
        return DB::table('grupo_estudiante')
            ->whereIn('id_grupo', $groupIds)
            ->orderBy('id_grupo')
            ->orderBy('id_estudiante')
            ->get(['id_grupo', 'id_estudiante']);
    }

    private function assertEveryGroupHasRoster(Collection $groups, Collection $enrollments): void
    {
        $groupsWithRoster = $enrollments->pluck('id_grupo')->unique();

        $withoutRoster = $groups->first(
            fn ($group) => ! $groupsWithRoster->contains($group->id_grupo)
        );

        if ($withoutRoster !== null) {
            throw ValidationException::withMessages([
                'grupos' => [
                    "El grupo {$withoutRoster->num_grupo} no tiene nómina cargada.",
                ],
            ]);
        }
    }

    private function assertRemovedGroupsHaveNoEntries(Exam $exam, array $groupIds): void
    {
        if ($groupIds === []) {
            return;
        }

        $hasEntries = DB::table('examen_estudiante')
            ->where('id_examen', $exam->id_examen)
            ->whereIn('id_grupo', $groupIds)
            ->exists();

        if ($hasEntries) {
            throw ValidationException::withMessages([
                'grupos' => [
                    'No se puede retirar un grupo que ya tiene registros de estudiantes en el examen.',
                ],
            ]);
        }
    }

    private function assertStudentsAreNotRepeated(Collection $enrollments): void
    {
        $hasRepeatedStudent = $enrollments
            ->groupBy('id_estudiante')
            ->contains(fn (Collection $studentGroups) => $studentGroups->count() > 1);

        if ($hasRepeatedStudent) {
            throw ValidationException::withMessages([
                'grupos' => [
                    'Un estudiante figura en más de uno de los grupos seleccionados.',
                ],
            ]);
        }
    }
}
