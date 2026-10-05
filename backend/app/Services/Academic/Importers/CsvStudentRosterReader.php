<?php

namespace App\Services\Academic\Importers;

use App\Exceptions\Academic\StudentRosterFileException;
use RuntimeException;

class CsvStudentRosterReader implements StudentRosterReader
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
        $handle = $this->openAsUtf8($path);

        try {
            $delimiter = $this->detectDelimiter($handle);

            $header = fgetcsv($handle, 0, $delimiter);

            if ($header === false) {
                throw new StudentRosterFileException(
                    'El archivo CSV no contiene encabezados.'
                );
            }

            $headerIndexes = $this->resolveHeaderIndexes($header);

            $rowNumber = 1;
            $dataRows = 0;

            while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
                $rowNumber++;

                if ($this->isEmptyRow($data)) {
                    continue;
                }

                $dataRows++;

                if ($dataRows > $this->maxRows) {
                    throw new StudentRosterFileException(
                        "La nómina supera el máximo de {$this->maxRows} filas por archivo."
                    );
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

    /**
     * Abre el archivo ya en UTF-8. Un CSV exportado desde Excel o WebSIS suele venir en
     * Windows-1252: si el contenido no es UTF-8 válido se convierte desde esa codificación.
     * Un archivo con caracteres de control no es texto (binario, UTF-16, imagen renombrada).
     * El archivo ya pasó el límite de 10 MB, así que se lee entero.
     *
     * @return resource
     */
    private function openAsUtf8(string $path)
    {
        $content = @file_get_contents($path);

        if ($content === false) {
            throw new RuntimeException(
                'No se pudo abrir el archivo CSV.'
            );
        }

        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $content) === 1) {
            throw new StudentRosterFileException(
                'El archivo CSV no es un archivo de texto. '
                . 'Guárdelo como CSV con codificación UTF-8 y vuelva a cargarlo.'
            );
        }

        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            throw new RuntimeException(
                'No se pudo abrir el archivo CSV.'
            );
        }

        fwrite($handle, $content);
        rewind($handle);

        return $handle;
    }

    private function detectDelimiter($handle): string
    {
        $position = ftell($handle);
        $firstLine = fgets($handle);

        if ($firstLine === false) {
            throw new StudentRosterFileException(
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
                throw new StudentRosterFileException(
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
