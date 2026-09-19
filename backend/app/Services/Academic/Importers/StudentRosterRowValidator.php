<?php

namespace App\Services\Academic\Importers;

class StudentRosterRowValidator
{
    private const MAX_SIS_CODE_LENGTH = 15;

    private const MAX_FIRST_NAMES_LENGTH = 50;

    /**
     * @return array<int, string>
     */
    public function validate(StudentRosterRow $row): array
    {
        $errors = [];

        if ($row->sisCode() === null) {
            $errors[] = 'missing_sis_code';
        }

        if ($row->lastNames() === null) {
            $errors[] = 'missing_last_names';
        }

        if ($row->firstNames() === null) {
            $errors[] = 'missing_first_names';
        }

        if (
            $row->sisCode() !== null
            && mb_strlen($row->sisCode()) > self::MAX_SIS_CODE_LENGTH
        ) {
            $errors[] = 'sis_code_too_long';
        }

        if (
            $row->firstNames() !== null
            && mb_strlen($row->firstNames()) > self::MAX_FIRST_NAMES_LENGTH
        ) {
            $errors[] = 'first_names_too_long';
        }

        return $errors;
    }
}