<?php

namespace App\Services\Academic\Importers;

use App\Exceptions\Academic\StudentRosterFileException;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use RuntimeException;

class XlsxStudentRosterReader implements StudentRosterReader
{
    private const REQUIRED_HEADERS = [
        'estudiante',
        'apellidos',
        'nombres',
    ];

    /** Valor por defecto cuando no hay aplicación (pruebas unitarias); el contenedor inyecta la configuración. */
    private const DEFAULT_MAX_ROWS = 2000;

    private int $maxRows;

    public function __construct(?int $maxRows = null)
    {
        $this->maxRows = $maxRows ?? self::DEFAULT_MAX_ROWS;
    }

    public function read(string $path): iterable
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException(
                'No se pudo leer el archivo XLSX.'
            );
        }

        $reader = new Xlsx();

        /*
         * Un XLSX se carga entero en memoria, así que el tope se comprueba antes de cargar:
         * listWorksheetInfo recorre el XML en streaming y no construye la hoja. Se mira la hoja
         * con más filas, porque la activa solo se conoce después de cargar. Como segunda defensa,
         * el filtro deja pasar solo el encabezado y las filas hasta el tope. No se usa
         * setReadDataOnly: perdería el formato numérico que conserva los ceros del código SIS.
         */
        $reader->setReadFilter($this->rowLimitFilter($this->maxRows + 1));

        try {
            $this->assertWithinRowLimit($reader->listWorksheetInfo($path));
            $spreadsheet = $reader->load($path);
        } catch (ReaderException $exception) {
            throw new StudentRosterFileException(
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
                throw new StudentRosterFileException(
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
     * El total de filas de una hoja es el de su última fila declarada, sin contar el encabezado.
     *
     * @param array<int, array<string, mixed>> $sheetsInfo
     */
    private function assertWithinRowLimit(array $sheetsInfo): void
    {
        $totalRows = 0;

        foreach ($sheetsInfo as $sheetInfo) {
            $totalRows = max($totalRows, (int) ($sheetInfo['totalRows'] ?? 0));
        }

        if ($totalRows - 1 > $this->maxRows) {
            throw new StudentRosterFileException(
                "La nómina supera el máximo de {$this->maxRows} filas por archivo."
            );
        }
    }

    private function rowLimitFilter(int $lastRow): IReadFilter
    {
        return new class ($lastRow) implements IReadFilter {
            private int $lastRow;

            public function __construct(int $lastRow)
            {
                $this->lastRow = $lastRow;
            }

            public function readCell($columnAddress, $row, $worksheetName = '')
            {
                return $row <= $this->lastRow;
            }
        };
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
                throw new StudentRosterFileException(
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
