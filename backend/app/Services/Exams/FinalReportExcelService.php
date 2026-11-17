<?php

namespace App\Services\Exams;

use Carbon\Carbon;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Convierte en Excel los datos del reporte final que arma FinalReportService (HU-051).
 *
 * Todo texto se escribe como cadena explícita: un nombre u observación que empiece con «=»
 * no debe ejecutarse como fórmula al abrir el archivo.
 */
class FinalReportExcelService
{
    private const NO_INCIDENTS_MESSAGE = 'No se registraron incidencias en este examen';

    /**
     * @return array{nombre_archivo: string, contenido: string}
     */
    public function render(array $report): array
    {
        $spreadsheet = new Spreadsheet();

        $this->summarySheet($spreadsheet->getActiveSheet(), $report);
        $this->studentsSheet($spreadsheet->createSheet(), $report['estudiantes']);
        $this->incidentsSheet($spreadsheet->createSheet(), $report['incidencias']);
        $spreadsheet->setActiveSheetIndex(0);

        $header = $report['cabecera'];
        $content = $this->toBinary($spreadsheet);

        $spreadsheet->disconnectWorksheets();

        return [
            'nombre_archivo' => $this->fileName($header['nombre_examen'], $header['fecha']),
            'contenido' => $content,
        ];
    }

    private function summarySheet(Worksheet $sheet, array $report): void
    {
        $sheet->setTitle('Resumen');

        $header = $report['cabecera'];
        $generatedAt = Carbon::parse($header['generado_en'])->setTimezone(config('sciem.zona_horaria'));

        $details = [
            ['Examen', $header['nombre_examen']],
            ['Materia', $header['materia'] ?? '—'],
            ['Docente', $header['docente'] ?? '—'],
            ['Fecha del examen', Carbon::parse($header['fecha'])->format('d/m/Y')],
            ['Hora de inicio programada', $header['hora_inicio'] ?? '—'],
            ['Apertura del control', $header['hora_apertura'] ?? '—'],
            ['Hora de fin', $header['hora_fin'] ?? '—'],
            ['Reporte generado', $generatedAt->format('d/m/Y H:i')],
        ];

        $row = 1;

        foreach ($details as [$label, $value]) {
            $this->cell($sheet, 1, $row, $label, true);
            $this->cell($sheet, 2, $row, $value);
            $row++;
        }

        $row++;

        $totals = $report['asistencia']['totales'];
        $showNotEnabled = $totals['no_habilitados'] > 0;
        $showUnclassified = $totals['sin_clasificar'] > 0;

        $labels = ['Grupo', 'Inscritos', 'A tiempo', 'Con retraso', 'Ausentes'];

        if ($showNotEnabled) {
            $labels[] = 'No habilitados';
        }

        if ($showUnclassified) {
            $labels[] = 'Sin clasificar';
        }

        $this->headerRow($sheet, $row++, $labels);

        foreach ($report['asistencia']['grupos'] as $group) {
            $values = [
                $group['num_grupo'],
                $group['inscritos'],
                $group['a_tiempo'],
                $group['con_retraso'],
                $group['ausentes'],
            ];

            if ($showNotEnabled) {
                $values[] = $group['no_habilitados'];
            }

            if ($showUnclassified) {
                $values[] = $group['sin_clasificar'];
            }

            $this->valuesRow($sheet, $row++, $values);
        }

        $values = [
            'Total',
            $totals['inscritos'],
            $totals['a_tiempo'],
            $totals['con_retraso'],
            $totals['ausentes'],
        ];

        if ($showNotEnabled) {
            $values[] = $totals['no_habilitados'];
        }

        if ($showUnclassified) {
            $values[] = $totals['sin_clasificar'];
        }

        $this->valuesRow($sheet, $row, $values, true);
        $this->widths($sheet, [20, 40, 12, 14, 12, 16, 16]);
    }

    private function studentsSheet(Worksheet $sheet, array $students): void
    {
        $sheet->setTitle('Estudiantes');

        $this->headerRow($sheet, 1, ['Código SIS', 'Nombre', 'Aula', 'Hora de ingreso', 'Estado']);

        $row = 2;

        foreach ($students as $student) {
            $this->valuesRow($sheet, $row++, [
                $student['cod_sis'],
                $student['nombre'],
                $student['ambiente'] ?? '—',
                $student['hora_ingreso'] ?? '—',
                $student['estado'],
            ]);
        }

        $this->widths($sheet, [16, 40, 10, 16, 22]);
    }

    private function incidentsSheet(Worksheet $sheet, array $incidents): void
    {
        $sheet->setTitle('Incidencias');

        $this->headerRow($sheet, 1, ['Hora', 'Estudiante', 'Tipo', 'Decisión del docente']);

        if ($incidents === []) {
            $this->cell($sheet, 1, 2, self::NO_INCIDENTS_MESSAGE);
        }

        $row = 2;

        foreach ($incidents as $incident) {
            $decision = $incident['decision']
                . ($incident['observacion'] ? ': ' . $incident['observacion'] : '');

            $this->valuesRow($sheet, $row++, [
                $incident['hora'],
                $incident['estudiante'] ?? '—',
                $incident['tipo'] ?? '—',
                $decision,
            ]);
        }

        $this->widths($sheet, [10, 36, 28, 60]);
    }

    private function headerRow(Worksheet $sheet, int $row, array $labels): void
    {
        foreach ($labels as $index => $label) {
            $this->cell($sheet, $index + 1, $row, $label, true);
        }
    }

    private function valuesRow(Worksheet $sheet, int $row, array $values, bool $bold = false): void
    {
        foreach ($values as $index => $value) {
            $this->cell($sheet, $index + 1, $row, $value, $bold);
        }
    }

    private function cell(Worksheet $sheet, int $column, int $row, mixed $value, bool $bold = false): void
    {
        $coordinate = Coordinate::stringFromColumnIndex($column) . $row;

        if (is_int($value) || is_float($value)) {
            $sheet->setCellValueExplicit($coordinate, $value, DataType::TYPE_NUMERIC);
        } else {
            $sheet->setCellValueExplicit($coordinate, (string) ($value ?? ''), DataType::TYPE_STRING);
        }

        if ($bold) {
            $sheet->getStyle($coordinate)->getFont()->setBold(true);
        }
    }

    private function widths(Worksheet $sheet, array $widths): void
    {
        foreach ($widths as $index => $width) {
            $sheet->getColumnDimensionByColumn($index + 1)->setWidth($width);
        }
    }

    private function toBinary(Spreadsheet $spreadsheet): string
    {
        ob_start();
        (new Xlsx($spreadsheet))->save('php://output');

        return (string) ob_get_clean();
    }

    private function fileName(string $examName, string $date): string
    {
        return 'reporte-final-' . Str::slug($examName) . '-' . $date . '.xlsx';
    }
}