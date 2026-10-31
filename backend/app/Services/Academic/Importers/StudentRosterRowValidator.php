<?php

namespace App\Services\Academic\Importers;

class StudentRosterRowValidator
{
    private const MAX_FIRST_NAMES_LENGTH = 50;

    private const MAX_LAST_NAMES_LENGTH = 30;

    private int $minSisCodeLength;

    private int $maxSisCodeLength;

    /**
     * Las cotas del código SIS salen de config('sciem.estudiante_cod_sis'); solo se
     * reciben por parámetro donde no hay aplicación (pruebas unitarias).
     */
    public function __construct(
        ?int $minSisCodeLength = null,
        ?int $maxSisCodeLength = null
    ) {
        $this->minSisCodeLength = $minSisCodeLength
            ?? (int) config('sciem.estudiante_cod_sis.min_length');
        $this->maxSisCodeLength = $maxSisCodeLength
            ?? (int) config('sciem.estudiante_cod_sis.max_length');
    }

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

        if ($row->sisCode() !== null) {
            if (preg_match('/^[0-9]+$/D', $row->sisCode()) !== 1) {
                $errors[] = 'sis_code_not_numeric';
            }

            $length = mb_strlen($row->sisCode());

            if ($length < $this->minSisCodeLength || $length > $this->maxSisCodeLength) {
                $errors[] = 'sis_code_invalid_length';
            }
        }

        if (
            $row->firstNames() !== null
            && mb_strlen($row->firstNames()) > self::MAX_FIRST_NAMES_LENGTH
        ) {
            $errors[] = 'first_names_too_long';
        }

        if (
            $row->lastNames() !== null
            && mb_strlen($row->lastNames()) > self::MAX_LAST_NAMES_LENGTH
        ) {
            $errors[] = 'last_names_too_long';
        }

        return $errors;
    }
}
