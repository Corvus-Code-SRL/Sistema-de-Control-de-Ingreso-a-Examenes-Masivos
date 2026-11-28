<?php

namespace Tests\Feature\Reports\Security;

use App\Models\Role;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use Tests\TestCase;

/**
 * HU-068: pruebas de exportación de bitácora.
 */
class AuditLogReportExcelTest extends TestCase
{
    use AuditLogReportExportTestSupport;

    public function test_administrator_downloads_valid_excel(): void
    {
        $this->seedOperations();

        $response = $this->get(
            $this->url('excel')
        )->assertOk();

        $response->assertHeader(
            'Content-Type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );

        $this->assertStringStartsWith(
            'PK',
            $response->getContent()
        );

        $this->assertStringContainsString(
            'reporte-bitacora-20261101-20261130.xlsx',
            $response->headers->get('Content-Disposition')
        );

        $book = $this->loadSpreadsheet(
            $response->getContent()
        );

        $this->assertSame([
            'Resumen',
            'Totales por acción',
            'Registros',
        ], $book->getSheetNames());

        $book->disconnectWorksheets();
    }

    public function test_excel_contains_correct_columns_and_order(): void
    {
        $this->seedOperations();

        $book = $this->workbook();

        $sheet = $book->getSheetByName('Registros');

        $this->assertSame([
            'Fecha y hora',
            'Usuario / Código SIS',
            'Acción',
            'Módulo',
            'Registro afectado',
        ], $sheet->rangeToArray('A1:E1')[0]);

        $this->assertSame(4, $sheet->getHighestRow());

        $this->assertSame(
            '04/11/2026 11:00',
            $sheet->getCell('A2')->getValue()
        );

        $this->assertStringContainsString(
            '20381',
            $sheet->getCell('B2')->getValue()
        );

        $this->assertSame(
            '02/11/2026 11:00',
            $sheet->getCell('A4')->getValue()
        );

        $book->disconnectWorksheets();
    }

    public function test_excel_contains_correct_totals(): void
    {
        $this->seedOperations();

        $book = $this->workbook();

        $summary = $book->getSheetByName('Resumen');

        $this->assertSame(
            3,
            $summary->getCell('B14')->getValue()
        );

        $this->assertSame(
            2,
            $summary->getCell('B15')->getValue()
        );

        $actions = $book->getSheetByName(
            'Totales por acción'
        );

        $this->assertSame(
            2,
            $actions->getCell('B2')->getValue()
        );

        $this->assertSame(
            1,
            $actions->getCell('B3')->getValue()
        );

        $book->disconnectWorksheets();
    }

    public function test_excel_applies_combined_filters(): void
    {
        $this->seedOperations();

        $book = $this->workbook([
            'q' => 'Jose Perez',
            'accion' => 'CREAR',
            'modulo' => 'examenes',
        ]);

        $sheet = $book->getSheetByName('Registros');

        $this->assertSame(2, $sheet->getHighestRow());

        $this->assertStringContainsString(
            '20381',
            $sheet->getCell('B2')->getValue()
        );

        $this->assertStringContainsString(
            '13',
            $sheet->getCell('E2')->getValue()
        );

        $book->disconnectWorksheets();
    }

    public function test_excel_exports_all_rows_from_page_two(): void
    {
        $this->seedMany(120);

        $book = $this->workbook(['page' => 2]);

        $sheet = $book->getSheetByName('Registros');

        $this->assertSame(
            121,
            $sheet->getHighestRow()
        );

        $book->disconnectWorksheets();
    }

    public function test_excel_exports_more_than_one_query_chunk(): void
    {
        $this->seedMany(501);

        $book = $this->workbook();

        $sheet = $book->getSheetByName('Registros');

        $this->assertSame(
            502,
            $sheet->getHighestRow()
        );

        $records = $sheet->rangeToArray('E2:E502');

        $this->assertCount(
            501,
            array_unique(array_column($records, 0))
        );

        $book->disconnectWorksheets();
    }

    public function test_excel_identifies_authenticated_administrator(): void
    {
        $this->seedOperations();

        Carbon::setTestNow(
            Carbon::parse('2026-11-26 15:30:00', 'UTC')
        );

        try {
            $book = $this->workbook([
                'generado_por' => 'Autor falso',
            ]);

            $sheet = $book->getSheetByName('Resumen');

            $this->assertSame(
                $this->admin->nombre_completo
                    . ' (SIS: '
                    . $this->admin->cod_sis
                    . ')',
                $sheet->getCell('B6')->getValue()
            );

            $this->assertSame(
                '26/11/2026 11:30',
                $sheet->getCell('B7')->getValue()
            );

            $book->disconnectWorksheets();
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_excel_preserves_text_without_formulas(): void
    {
        $actor = $this->namedAccount(
            '=1+1',
            'Prueba',
            '99881',
            Role::DOCENTE
        );

        $this->logRow(
            $actor,
            'CREAR',
            'examen',
            null,
            ['id_examen' => 90],
            '2026-11-10 15:00:00+00'
        );

        $book = $this->workbook();

        $cell = $book->getSheetByName('Registros')
            ->getCell('B2');

        $this->assertStringStartsWith(
            '=1+1',
            $cell->getValue()
        );

        $this->assertFalse($cell->isFormula());

        $this->assertSame(
            DataType::TYPE_STRING,
            $cell->getDataType()
        );

        $book->disconnectWorksheets();
    }
}
