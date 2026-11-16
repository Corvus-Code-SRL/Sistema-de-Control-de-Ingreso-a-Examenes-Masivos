<?php

namespace App\Services\Exams\Importers;

use App\Services\Academic\Importers\StudentRosterRow;

/**
 * Valida una fila del archivo de lista de habilitados (HU-028).
 *
 * Solo importa el código SIS: a diferencia de la nómina (HU-021), el
 * archivo de HU-028 puede traer únicamente los códigos. Los nombres y
 * apellidos, si vienen, son informativos y no invalidan la fila.
 *
 * Reutiliza StudentRosterRow (mismo value object de HU-021) pero con
 * reglas propias: nada de missing_last_names ni missing_first_names.
 */
class EligibleListRowValidator
{
    private int $minSisCodeLength;

    private int $maxSisCodeLength;

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
            $errors[] = 'sis_code_null';

            return $errors;
        }

        if (preg_match('/^[0-9]+$/D', $row->sisCode()) !== 1) {
            $errors[] = 'sis_code_not_numeric';
        }

        $length = mb_strlen($row->sisCode());

        if ($length < $this->minSisCodeLength || $length > $this->maxSisCodeLength) {
            $errors[] = 'sis_code_invalid_length';
        }

        return $errors;
    }
}
