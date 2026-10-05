<?php

namespace Tests\Concerns;

use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Fabrica archivos de nómina reales (CSV y XLSX) y hace las peticiones de preview y
 * confirmación de HU-21. Los archivos temporales se borran en tearDown.
 */
trait BuildsRosterFiles
{
    /** @var array<int, string> */
    private array $rosterTemporaryFiles = [];

    /** @after */
    public function deleteRosterTemporaryFiles(): void
    {
        foreach ($this->rosterTemporaryFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        $this->rosterTemporaryFiles = [];
    }

    /**
     * @return array<int, array{0: string, 1: string, 2: string}> código SIS, apellidos y nombres
     */
    protected function rosterRows(int $count, int $firstSis = 300000001): array
    {
        $rows = [];

        for ($i = 0; $i < $count; $i++) {
            $rows[] = [(string) ($firstSis + $i), 'APELLIDO' . $i . ' SEGUNDO', 'NOMBRE' . $i];
        }

        return $rows;
    }

    /** @param array<int, array<int, string>> $rows */
    protected function csvContent(array $rows, string $delimiter = ','): string
    {
        $content = implode($delimiter, ['Estudiante', 'Apellidos', 'Nombres']) . "\n";

        foreach ($rows as $row) {
            $content .= implode($delimiter, $row) . "\n";
        }

        return $content;
    }

    protected function rosterFile(string $name, string $content): UploadedFile
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('sciem_roster_', true) . '_' . $name;
        file_put_contents($path, $content);
        $this->rosterTemporaryFiles[] = $path;

        return new UploadedFile($path, $name, null, null, true);
    }

    /** @param array<int, array<int, string>> $rows */
    protected function csvFile(array $rows, string $name = 'nomina.csv'): UploadedFile
    {
        return $this->rosterFile($name, $this->csvContent($rows));
    }

    /** @param array<int, array<int, string>> $rows */
    protected function xlsxFile(array $rows, string $name = 'nomina.xlsx'): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        foreach (['Estudiante', 'Apellidos', 'Nombres'] as $index => $header) {
            $sheet->setCellValueExplicitByColumnAndRow($index + 1, 1, $header, DataType::TYPE_STRING);
        }

        $line = 2;

        foreach ($rows as $row) {
            foreach ($row as $index => $value) {
                $sheet->setCellValueExplicitByColumnAndRow($index + 1, $line, $value, DataType::TYPE_STRING);
            }

            $line++;
        }

        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('sciem_roster_', true) . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();
        $this->rosterTemporaryFiles[] = $path;

        return new UploadedFile($path, $name, null, null, true);
    }

    /** @return \Illuminate\Testing\TestResponse */
    protected function previewRoster(UploadedFile $file, $groupId)
    {
        return $this->post(
            "/api/grupos/{$groupId}/nomina/preview",
            ['archivo' => $file],
            ['Accept' => 'application/json']
        );
    }

    /** @return \Illuminate\Testing\TestResponse */
    protected function confirmRoster(string $token, $groupId)
    {
        return $this->postJson("/api/grupos/{$groupId}/nomina/confirm", ['token' => $token]);
    }
}
