<?php

namespace App\Services\Academic\Importers;

use App\Models\Student;
use Illuminate\Support\Facades\DB;
use App\Support\RecordStatus;

class StudentRosterDatabaseMatcher
{
    /**
     * @return array<int, StudentRosterDatabaseMatch>
     */
    public function classify(
        int $groupId,
        StudentRosterAnalysisResult $analysis
    ): array {
        $validRows = $this->validRows($analysis);

        if ($validRows === []) {
            return [];
        }

        $sisCodes = $this->sisCodes($validRows);

        $students = Student::query()
            ->whereIn('cod_sis', $sisCodes)
            ->get([
                'id_estudiante',
                'cod_sis',
            ]);

        $studentsBySis = [];

        foreach ($students as $student) {
            $studentsBySis[
                'sis:' . $student->cod_sis
            ] = $student;
        }

        $studentIds = $students
            ->pluck('id_estudiante')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $enrollmentStatusByStudentId = [];

        if ($studentIds !== []) {
            $enrollments = DB::table('grupo_estudiante')
                ->where('id_grupo', $groupId)
                ->whereIn('id_estudiante', $studentIds)
                ->get([
                    'id_estudiante',
                    'estado',
                ]);

            foreach ($enrollments as $enrollment) {
                $enrollmentStatusByStudentId[
                    (int) $enrollment->id_estudiante
                ] = $enrollment->estado;
            }
        }

        $matches = [];

        foreach ($validRows as $rowAnalysis) {
            $sisCode = $rowAnalysis->row()->sisCode();

            if ($sisCode === null) {
                continue;
            }

            $student = $studentsBySis['sis:' . $sisCode] ?? null;

            if ($student === null) {
                $matches[] = new StudentRosterDatabaseMatch(
                    $rowAnalysis,
                    null,
                    StudentRosterDatabaseMatch::NEW_STUDENT
                );

                continue;
            }

            $studentId = (int) $student->id_estudiante;

            $enrollmentStatus = $enrollmentStatusByStudentId[
                $studentId
            ] ?? null;

            if ($enrollmentStatus === RecordStatus::ACTIVE) {
                $status = StudentRosterDatabaseMatch::ALREADY_ENROLLED;
            } elseif ($enrollmentStatus === RecordStatus::INACTIVE) {
                $status = StudentRosterDatabaseMatch::INACTIVE_ENROLLMENT;
            } else {
                $status = StudentRosterDatabaseMatch::EXISTING_STUDENT;
            }

            $matches[] = new StudentRosterDatabaseMatch(
                $rowAnalysis,
                $studentId,
                $status
            );
        }

        return $matches;
    }

    /**
     * @return array<int, StudentRosterRowAnalysis>
     */
    private function validRows(
        StudentRosterAnalysisResult $analysis
    ): array {
        return array_values(array_filter(
            $analysis->rows(),
            static function (
                StudentRosterRowAnalysis $row
            ): bool {
                return $row->isValid();
            }
        ));
    }

    /**
     * @param array<int, StudentRosterRowAnalysis> $rows
     * @return array<int, string>
     */
    private function sisCodes(array $rows): array
    {
        $sisCodes = [];

        foreach ($rows as $rowAnalysis) {
            $sisCode = $rowAnalysis->row()->sisCode();

            if ($sisCode !== null) {
                $sisCodes[] = $sisCode;
            }
        }

        return array_values(array_unique($sisCodes));
    }
}