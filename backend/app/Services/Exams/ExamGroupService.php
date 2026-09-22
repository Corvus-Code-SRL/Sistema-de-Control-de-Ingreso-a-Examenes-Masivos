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
 * Vincula grupos válidos con un examen y genera su nómina habilitada.
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

            $enrollments = $this->activeEnrollments($groupIds);
            $this->assertEveryGroupHasRoster($groups, $enrollments);
            $this->assertStudentsAreNotRepeated($enrollments);

            /*
             * examen_estudiante referencia grupo_examen. Primero se retira la nómina
             * anterior y después se sincronizan los grupos para respetar esa FK.
             */
            DB::table('examen_estudiante')
                ->where('id_examen', $exam->id_examen)
                ->delete();

            $exam->groups()->sync($groupIds);

            DB::table('examen_estudiante')->insert(
                $enrollments->map(fn ($enrollment) => [
                    'id_examen' => $exam->id_examen,
                    'id_estudiante' => $enrollment->id_estudiante,
                    'id_grupo' => $enrollment->id_grupo,
                    'estado_habilitacion' => 'HABILITADO',
                    'estado_ingreso' => 'NO_INGRESO',
                ])->all()
            );

            return $exam->fresh([
                'examType',
                'subject',
                'career',
                'classrooms',
                'groups' => fn ($query) => $query->withActiveStudentCount(),
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

    private function activeEnrollments(array $groupIds): Collection
    {
        return DB::table('grupo_estudiante')
            ->whereIn('id_grupo', $groupIds)
            ->where('estado', RecordStatus::ACTIVE)
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
                    "El grupo {$withoutRoster->num_grupo} no tiene una nómina activa cargada.",
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
