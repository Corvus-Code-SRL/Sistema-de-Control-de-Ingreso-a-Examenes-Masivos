<?php

namespace App\Services\Academic\Importers;

use RuntimeException;

class CsvStudentRosterReader implements StudentRosterReader
{
    private const REQUIRED_HEADERS = [
        'estudiante',
        'apellidos',
        'nombres',
    ];

    public function read(string $path): iterable
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException(
                'No se pudo abrir el archivo CSV.'
            );
        }

        try {
            $delimiter = $this->detectDelimiter($handle);

            $header = fgetcsv($handle, 0, $delimiter);

            if ($header === false) {
                throw new RuntimeException(
                    'El archivo CSV no contiene encabezados.'
                );
            }

            $headerIndexes = $this->resolveHeaderIndexes($header);

            $rowNumber = 1;

            while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
                $rowNumber++;

                if ($this->isEmptyRow($data)) {
                    continue;
                }

                yield new StudentRosterRow(
                    $rowNumber,
                    $this->cellValue(
                        $data,
                        $headerIndexes['estudiante']
                    ),
                    $this->cellValue(
                        $data,
                        $headerIndexes['apellidos']
                    ),
                    $this->cellValue(
                        $data,
                        $headerIndexes['nombres']
                    )
                );
            }
        } finally {
            fclose($handle);
        }
    }

    private function detectDelimiter($handle): string
    {
        $position = ftell($handle);
        $firstLine = fgets($handle);

        if ($firstLine === false) {
            throw new RuntimeException(
                'El archivo CSV está vacío.'
            );
        }

        fseek($handle, $position);

        $semicolonCount = substr_count($firstLine, ';');
        $commaCount = substr_count($firstLine, ',');

        return $semicolonCount > $commaCount ? ';' : ',';
    }

    /**
     * @param array<int, string|null> $header
     * @return array<string, int>
     */
    private function resolveHeaderIndexes(array $header): array
    {
        $normalizedHeaders = [];

        foreach ($header as $index => $value) {
            $normalizedHeaders[
                $this->normalizeHeader((string) $value)
            ] = $index;
        }

        foreach (self::REQUIRED_HEADERS as $requiredHeader) {
            if (!array_key_exists(
                $requiredHeader,
                $normalizedHeaders
            )) {
                throw new RuntimeException(
                    "Falta la columna requerida: {$requiredHeader}."
                );
            }
        }

        return [
            'estudiante' => $normalizedHeaders['estudiante'],
            'apellidos' => $normalizedHeaders['apellidos'],
            'nombres' => $normalizedHeaders['nombres'],
        ];
    }

    private function normalizeHeader(string $value): string
    {
        $value = preg_replace('/^\xEF\xBB\xBF/', '', $value);

        return mb_strtolower(trim($value));
    }

    /**
     * @param array<int, string|null> $row
     */
    private function cellValue(array $row, int $index): ?string
    {
        if (!array_key_exists($index, $row)) {
            return null;
        }

        $value = trim((string) $row[$index]);

        return $value === '' ? null : $value;
    }

    /**
     * @param array<int, string|null> $row
     */
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }
}