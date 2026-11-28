<?php

namespace App\Services\Reports\Security;

use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * HU-068: genera el reporte de bitácora en formato Excel.
 */
class AuditLogReportExcelService
{
    /**
     * @return array{nombre_archivo: string, contenido: string}
     */
    public function render(array $report): array
    {
        $spreadsheet = new Spreadsheet();

        try {
            $spreadsheet->getProperties()
                ->setTitle('Reporte de bitácora')
                ->setCreator($report['cabecera']['generado_por']);

            $this->summarySheet(
                $spreadsheet->getActiveSheet(),
                $report
            );

            $this->actionsSheet(
                $spreadsheet->createSheet(),
                $report['totales']['por_accion']
            );

            $this->recordsSheet(
                $spreadsheet->createSheet(),
                $report['registros']
            );

            $spreadsheet->setActiveSheetIndex(0);

            $content = $this->toBinary($spreadsheet);
        } finally {
            $spreadsheet->disconnectWorksheets();
        }

        $filters = $report['filtros'];

        return [
            'nombre_archivo' => 'reporte-bitacora-'
                . str_replace('-', '', $filters['desde'])
                . '-'
                . str_replace('-', '', $filters['hasta'])
                . '.xlsx',
            'contenido' => $content,
        ];
    }

    private function summarySheet(
        Worksheet $sheet,
        array $report
    ): void {
        $sheet->setTitle('Resumen');

        $header = $report['cabecera'];
        $filters = $report['filtros'];
        $totals = $report['totales'];

        $this->cell($sheet, 1, 1, 'SCIEM', true);
        $this->cell($sheet, 1, 2, 'Reporte de bitácora', true);

        $details = [
            ['Desde', Carbon::parse($filters['desde'])->format('d/m/Y')],
            ['Hasta', Carbon::parse($filters['hasta'])->format('d/m/Y')],
            ['Generado por', $header['generado_por']],
            ['Fecha de generación', $this->formatDateTime($header['generado_en'])],
            ['Zona horaria', 'America/La_Paz'],
            ['Usuario', $filters['q'] ?? 'Todos los usuarios'],
            ['Acción', $header['accion']],
            ['Módulo', $header['modulo']],
        ];

        $row = 4;

        foreach ($details as [$label, $value]) {
            $this->cell($sheet, 1, $row, $label, true);
            $this->cell($sheet, 2, $row, $value);
            $row++;
        }

        $row++;

        $this->valuesRow(
            $sheet,
            $row++,
            ['Resumen de operaciones', 'Cantidad'],
            true
        );

        $this->valuesRow($sheet, $row++, [
            'Total de operaciones',
            $totals['total_operaciones'],
        ]);

        $this->valuesRow($sheet, $row, [
            'Usuarios distintos',
            $totals['total_usuarios'],
        ]);

        $this->widths($sheet, [32, 65]);

        $sheet->getStyle('B4:B11')
            ->getAlignment()
            ->setWrapText(true);
    }

    private function actionsSheet(
        Worksheet $sheet,
        array $actions
    ): void {
        $sheet->setTitle('Totales por acción');

        $this->valuesRow(
            $sheet,
            1,
            ['Acción', 'Cantidad'],
            true
        );

        $row = 2;

        foreach ($actions as $action) {
            $this->valuesRow($sheet, $row++, [
                $action['etiqueta'],
                $action['total'],
            ]);
        }

        $this->widths($sheet, [45, 18]);

        $sheet->freezePane('A2');
    }

    private function recordsSheet(
        Worksheet $sheet,
        iterable $records
    ): void {
        $sheet->setTitle('Registros');

        $this->valuesRow($sheet, 1, [
            'Fecha y hora',
            'Usuario / Código SIS',
            'Acción',
            'Módulo',
            'Registro afectado',
        ], true);

        $row = 2;

        foreach ($records as $entry) {
            $actor = $entry['usuario']['etiqueta'];
            $sis = $entry['usuario']['cod_sis'] ?? null;

            if ($sis !== null && $sis !== '') {
                $actor .= "\nSIS: " . $sis;
            }

            $this->valuesRow($sheet, $row++, [
                $this->formatDateTime($entry['fecha_hora']),
                $actor,
                $entry['accion']['etiqueta'],
                $entry['modulo']['etiqueta'],
                $entry['registro']['descripcion'],
            ]);
        }

        $this->widths($sheet, [23, 42, 36, 28, 65]);

        $sheet->freezePane('A2');

        $lastRow = $row - 1;

        $sheet->setAutoFilter('A1:E' . $lastRow);

        if ($lastRow >= 2) {
            $sheet->getStyle('A2:E' . $lastRow)
                ->getAlignment()
                ->setWrapText(true)
                ->setVertical('top');
        }
    }

    private function formatDateTime(string $value): string
    {
        return Carbon::parse($value)
            ->setTimezone(
                config('sciem.zona_horaria', 'America/La_Paz')
            )
            ->format('d/m/Y H:i');
    }

    private function valuesRow(
        Worksheet $sheet,
        int $row,
        array $values,
        bool $bold = false
    ): void {
        foreach ($values as $index => $value) {
            $this->cell(
                $sheet,
                $index + 1,
                $row,
                $value,
                $bold
            );
        }
    }

    private function cell(
        Worksheet $sheet,
        int $column,
        int $row,
        string|int|float|null $value,
        bool $bold = false
    ): void {
        $coordinate = Coordinate::stringFromColumnIndex($column)
            . $row;

        if (is_int($value) || is_float($value)) {
            $sheet->setCellValueExplicit(
                $coordinate,
                $value,
                DataType::TYPE_NUMERIC
            );
        } else {
            $sheet->setCellValueExplicit(
                $coordinate,
                (string) ($value ?? ''),
                DataType::TYPE_STRING
            );
        }

        if ($bold) {
            $sheet->getStyle($coordinate)
                ->getFont()
                ->setBold(true);
        }
    }

    private function widths(
        Worksheet $sheet,
        array $widths
    ): void {
        foreach ($widths as $index => $width) {
            $sheet->getColumnDimensionByColumn($index + 1)
                ->setWidth($width);
        }
    }

    private function toBinary(Spreadsheet $spreadsheet): string
    {
        ob_start();

        try {
            (new Xlsx($spreadsheet))->save('php://output');

            return (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }
}
