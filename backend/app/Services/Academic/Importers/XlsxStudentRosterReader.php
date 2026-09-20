<?php

namespace App\Services\Academic\Importers;

use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use RuntimeException;

class XlsxStudentRosterReader implements StudentRosterReader
{
    private const REQUIRED_HEADERS = [
        'estudiante',
        'apellidos',
        'nombres',
    ];

    public function read(string $path): iterable
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException(
                'No se pudo leer el archivo XLSX.'
            );
        }

        $reader = new Xlsx();

        try {
            $spreadsheet = $reader->load($path);
        } catch (\Throwable $exception) {
            throw new RuntimeException(
                'No se pudo procesar el archivo XLSX.',
                0,
                $exception
            );
        }

        try {
            $sheet = $spreadsheet->getActiveSheet();

            $highestColumn = $sheet->getHighestDataColumn();
            $highestRow = $sheet->getHighestDataRow();

            $headerRow = $sheet->rangeToArray(
                'A1:' . $highestColumn . '1',
                null,
                true,
                true,
                true
            )[1];

            if ($this->isEmptyRow($headerRow)) {
                throw new RuntimeException(
                    'El archivo XLSX está vacío.'
                );
            }

            $headers = $this->mapHeaders($headerRow);

            $this->validateRequiredHeaders($headers);

            $rows = [];

            for ($rowNumber = 2; $rowNumber <= $highestRow; $rowNumber++) {
                $row = $sheet->rangeToArray(
                    'A' . $rowNumber
                        . ':'
                        . $highestColumn
                        . $rowNumber,
                    null,
                    true,
                    true,
                    true
                )[$rowNumber];

                if ($this->isEmptyRow($row)) {
                    continue;
                }

                $rows[] = new StudentRosterRow(
                    $rowNumber,
                    $this->cellValue(
                        $row[$headers['estudiante']] ?? null
                    ),
                    $this->cellValue(
                        $row[$headers['apellidos']] ?? null
                    ),
                    $this->cellValue(
                        $row[$headers['nombres']] ?? null
                    )
                );
            }

            return $rows;
        } finally {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, string>
     */
    private function mapHeaders(array $row): array
    {
        $headers = [];

        foreach ($row as $column => $value) {
            $header = $this->normalizeHeader($value);

            if ($header === null) {
                continue;
            }

            $headers[$header] = $column;
        }

        return $headers;
    }

    /**
     * @param array<string, string> $headers
     */
    private function validateRequiredHeaders(array $headers): void
    {
        foreach (self::REQUIRED_HEADERS as $requiredHeader) {
            if (!array_key_exists($requiredHeader, $headers)) {
                throw new RuntimeException(
                    'Falta la columna requerida: '
                    . $requiredHeader
                    . '.'
                );
            }
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if ($this->cellValue($value) !== null) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param mixed $value
     */
    private function normalizeHeader($value): ?string
    {
        $value = $this->cellValue($value);

        if ($value === null) {
            return null;
        }

        return mb_strtolower($value, 'UTF-8');
    }

    /**
     * @param mixed $value
     */
    private function cellValue($value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            $value = $value ? '1' : '0';
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}