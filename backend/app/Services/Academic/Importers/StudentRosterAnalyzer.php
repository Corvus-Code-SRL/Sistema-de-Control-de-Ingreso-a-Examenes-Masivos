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
        $rowsBySisCode = $this->groupBySisCode($rows);

        $analyzedRows = [];

        foreach ($rows as $row) {
            $errors = $this->validator->validate($row);

            $duplicateError = $this->duplicateError($row, $rowsBySisCode);

            if ($duplicateError !== null) {
                $errors[] = $duplicateError;
            }

            $analyzedRows[] = new StudentRosterRowAnalysis(
                $row,
                $errors
            );
        }

        return new StudentRosterAnalysisResult($analyzedRows);
    }

    /**
     * Un código SIS repetido con datos idénticos se importa una sola vez: la primera
     * fila vale y las demás se reportan como duplicadas. Si las filas difieren en
     * cualquier campo no hay forma de saber cuál es la correcta: ninguna se importa.
     *
     * @param array<string, array<int, StudentRosterRow>> $rowsBySisCode
     */
    private function duplicateError(
        StudentRosterRow $row,
        array $rowsBySisCode
    ): ?string {
        $sisCode = $row->sisCode();

        if ($sisCode === null || count($rowsBySisCode[$sisCode]) < 2) {
            return null;
        }

        $sameSisCode = $rowsBySisCode[$sisCode];

        if (!$this->areIdentical($sameSisCode)) {
            return 'conflicting_duplicate_in_file';
        }

        return $sameSisCode[0] === $row
            ? null
            : 'duplicate_row_in_file';
    }

    /**
     * @param array<int, StudentRosterRow> $rows
     */
    private function areIdentical(array $rows): bool
    {
        $first = $rows[0];

        foreach ($rows as $row) {
            if (
                $row->lastNames() !== $first->lastNames()
                || $row->firstNames() !== $first->firstNames()
            ) {
                return false;
            }
        }

        return true;
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
     * @return array<string, array<int, StudentRosterRow>>
     */
    private function groupBySisCode(array $rows): array
    {
        $grouped = [];

        foreach ($rows as $row) {
            $sisCode = $row->sisCode();

            if ($sisCode === null) {
                continue;
            }

            $grouped[$sisCode][] = $row;
        }

        return $grouped;
    }
}
