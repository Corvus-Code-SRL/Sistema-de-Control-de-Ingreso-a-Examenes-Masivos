<?php

namespace App\Services\Academic\Importers;

class StudentRosterAnalyzer
{
    private StudentRosterRowValidator $validator;

    public function __construct(
        StudentRosterRowValidator $validator
    ) {
        $this->validator = $validator;
    }

    public function analyze(iterable $rows): StudentRosterAnalysisResult
    {
        $rows = $this->toArray($rows);
        $sisCodeOccurrences = $this->countSisCodeOccurrences($rows);

        $analyzedRows = [];

        foreach ($rows as $row) {
            $errors = $this->validator->validate($row);

            $sisCode = $row->sisCode();

            if (
                $sisCode !== null
                && $sisCodeOccurrences[$sisCode] > 1
            ) {
                $errors[] = 'duplicate_sis_code_in_file';
            }

            $analyzedRows[] = new StudentRosterRowAnalysis(
                $row,
                $errors
            );
        }

        return new StudentRosterAnalysisResult($analyzedRows);
    }

    /**
     * @return array<int, StudentRosterRow>
     */
    private function toArray(iterable $rows): array
    {
        if (is_array($rows)) {
            return array_values($rows);
        }

        return iterator_to_array($rows, false);
    }

    /**
     * @param array<int, StudentRosterRow> $rows
     * @return array<string, int>
     */
    private function countSisCodeOccurrences(array $rows): array
    {
        $occurrences = [];

        foreach ($rows as $row) {
            $sisCode = $row->sisCode();

            if ($sisCode === null) {
                continue;
            }

            if (!isset($occurrences[$sisCode])) {
                $occurrences[$sisCode] = 0;
            }

            $occurrences[$sisCode]++;
        }

        return $occurrences;
    }
}