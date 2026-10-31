<?php

namespace App\Services\Academic\Importers;

use InvalidArgumentException;

class StudentRosterReaderResolver
{
    private CsvStudentRosterReader $csvReader;

    private XlsxStudentRosterReader $xlsxReader;

    public function __construct(
        CsvStudentRosterReader $csvReader,
        XlsxStudentRosterReader $xlsxReader
    ) {
        $this->csvReader = $csvReader;
        $this->xlsxReader = $xlsxReader;
    }

    public function resolve(string $extension): StudentRosterReader
    {
        $extension = mb_strtolower(
            trim($extension),
            'UTF-8'
        );

        if ($extension === 'csv') {
            return $this->csvReader;
        }

        if ($extension === 'xlsx') {
            return $this->xlsxReader;
        }

        throw new InvalidArgumentException(
            'Formato de nómina no soportado.'
        );
    }
}