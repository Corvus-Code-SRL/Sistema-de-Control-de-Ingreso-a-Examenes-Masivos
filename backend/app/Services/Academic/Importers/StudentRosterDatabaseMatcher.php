<?php

namespace App\Services\Academic\Importers;

use App\Models\Student;
use Illuminate\Support\Facades\DB;

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

        /*
         * Estar en la nómina es tener una fila en grupo_estudiante: el estado de la
         * fila no se lee, la exactitud de la lista es responsabilidad de WEBSIS.
         */
        $enrolledStudentIds = [];

        if ($studentIds !== []) {
            $enrolledStudentIds = DB::table('grupo_estudiante')
                ->where('id_grupo', $groupId)
                ->whereIn('id_estudiante', $studentIds)
                ->pluck('id_estudiante')
                ->map(static fn ($id): int => (int) $id)
                ->flip()
                ->all();
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

            $status = isset($enrolledStudentIds[$studentId])
                ? StudentRosterDatabaseMatch::ALREADY_ENROLLED
                : StudentRosterDatabaseMatch::EXISTING_STUDENT;

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