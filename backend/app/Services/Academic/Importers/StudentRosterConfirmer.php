<?php

namespace App\Services\Academic\Importers;

use App\Support\RecordStatus;
use Illuminate\Support\Facades\DB;
use LogicException;

class StudentRosterConfirmer
{
    private StudentRosterDatabaseMatcher $matcher;

    private StudentRosterStudentCreator $studentCreator;

    public function __construct(
        StudentRosterDatabaseMatcher $matcher,
        StudentRosterStudentCreator $studentCreator
    ) {
        $this->matcher = $matcher;
        $this->studentCreator = $studentCreator;
    }

    public function confirm(
        int $groupId,
        StudentRosterAnalysisResult $analysis
    ): StudentRosterConfirmationResult {
        $enrollmentDate = now()->toDateString();

        return DB::transaction(function () use (
            $groupId,
            $analysis,
            $enrollmentDate
        ): StudentRosterConfirmationResult {
            $matches = $this->matcher->classify(
                $groupId,
                $analysis
            );

            $createdStudents = 0;
            $enrolledStudents = 0;
            $alreadyEnrolled = 0;
            $inactiveEnrollments = 0;

            foreach ($matches as $match) {
                if (
                    $match->status()
                    === StudentRosterDatabaseMatch::NEW_STUDENT
                ) {
                    $student = $this->studentCreator->create(
                        $match->rowAnalysis()->row()
                    );

                    $createdStudents++;

                    $this->enrollStudent(
                        $groupId,
                        (int) $student->id_estudiante,
                        $enrollmentDate
                    );

                    $enrolledStudents++;

                    continue;
                }

                if (
                    $match->status()
                    === StudentRosterDatabaseMatch::EXISTING_STUDENT
                ) {
                    $studentId = $match->studentId();

                    if ($studentId === null) {
                        throw new LogicException(
                            'El estudiante existente no tiene identificador.'
                        );
                    }

                    $this->enrollStudent(
                        $groupId,
                        $studentId,
                        $enrollmentDate
                    );

                    $enrolledStudents++;

                    continue;
                }

                if (
                    $match->status()
                    === StudentRosterDatabaseMatch::ALREADY_ENROLLED
                ) {
                    $alreadyEnrolled++;

                    continue;
                }

                if (
                    $match->status()
                    === StudentRosterDatabaseMatch::INACTIVE_ENROLLMENT
                ) {
                    $inactiveEnrollments++;

                    continue;
                }

                throw new LogicException(
                    'Estado de clasificación de nómina no soportado.'
                );
            }

            return new StudentRosterConfirmationResult(
                $analysis->totalRows(),
                $analysis->inconsistentRows(),
                $createdStudents,
                $enrolledStudents,
                $alreadyEnrolled,
                $inactiveEnrollments
            );
        });
    }

    private function enrollStudent(
        int $groupId,
        int $studentId,
        string $enrollmentDate
    ): void {
        DB::table('grupo_estudiante')->insert([
            'id_grupo' => $groupId,
            'id_estudiante' => $studentId,
            'fecha_inscripcion' => $enrollmentDate,
            'estado' => RecordStatus::ACTIVE,
        ]);
    }
}