<?php

namespace Tests\Unit\Services\Academic\Importers;

use App\Services\Academic\Importers\XlsxStudentRosterReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class XlsxStudentRosterReaderTest extends TestCase
{
    /**
     * @var array<int, string>
     */
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    public function test_lee_columnas_requeridas_e_ignora_columnas_adicionales(): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->fromArray([
            [
                'Estudiante',
                'Apellidos',
                'Nombres',
                '1er Parcial',
                '2do Parcial',
            ],
            [
                '20230001',
                'ROJAS FLORES',
                'CARLOS ALBERTO',
                75,
                80,
            ],
        ]);

        $path = $this->saveSpreadsheet($spreadsheet);

        $rows = $this->readRows($path);

        $this->assertCount(1, $rows);
        $this->assertSame(2, $rows[0]->rowNumber());
        $this->assertSame('20230001', $rows[0]->sisCode());
        $this->assertSame('ROJAS FLORES', $rows[0]->lastNames());
        $this->assertSame('CARLOS ALBERTO', $rows[0]->firstNames());
    }

    public function test_encuentra_encabezados_aunque_cambie_el_orden(): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->fromArray([
            [
                'Nombres',
                '1er Parcial',
                'Estudiante',
                'Apellidos',
            ],
            [
                'MARIA',
                90,
                '20230002',
                'PEREZ ROJAS',
            ],
        ]);

        $path = $this->saveSpreadsheet($spreadsheet);

        $rows = $this->readRows($path);

        $this->assertCount(1, $rows);
        $this->assertSame('20230002', $rows[0]->sisCode());
        $this->assertSame('PEREZ ROJAS', $rows[0]->lastNames());
        $this->assertSame('MARIA', $rows[0]->firstNames());
    }

    public function test_ignora_filas_vacias_y_conserva_numero_real_de_fila(): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->fromArray([
            [
                'Estudiante',
                'Apellidos',
                'Nombres',
            ],
            [
                '20230003',
                'LOPEZ',
                'JUAN',
            ],
            [
                null,
                null,
                null,
            ],
            [
                '20230004',
                'MAMANI',
                'ANA',
            ],
        ]);

        $path = $this->saveSpreadsheet($spreadsheet);

        $rows = $this->readRows($path);

        $this->assertCount(2, $rows);
        $this->assertSame(2, $rows[0]->rowNumber());
        $this->assertSame(4, $rows[1]->rowNumber());
    }

    public function test_convierte_celdas_vacias_en_null(): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->fromArray([
            [
                'Estudiante',
                'Apellidos',
                'Nombres',
            ],
            [
                '20230005',
                '',
                'PEDRO',
            ],
        ]);

        $path = $this->saveSpreadsheet($spreadsheet);

        $rows = $this->readRows($path);

        $this->assertCount(1, $rows);
        $this->assertNull($rows[0]->lastNames());
    }

    public function test_falla_si_falta_un_encabezado_requerido(): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->fromArray([
            [
                'Estudiante',
                'Nombres',
            ],
            [
                '20230006',
                'LUIS',
            ],
        ]);

        $path = $this->saveSpreadsheet($spreadsheet);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Falta la columna requerida: apellidos.'
        );

        (new XlsxStudentRosterReader())->read($path);
    }

    public function test_falla_si_el_archivo_esta_vacio(): void
    {
        $spreadsheet = new Spreadsheet();

        $path = $this->saveSpreadsheet($spreadsheet);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'El archivo XLSX está vacío.'
        );

        (new XlsxStudentRosterReader())->read($path);
    }

    public function test_conserva_ceros_iniciales_del_sis_formateado_en_excel(): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('A1', 'Estudiante');
        $sheet->setCellValue('B1', 'Apellidos');
        $sheet->setCellValue('C1', 'Nombres');

        $sheet->setCellValue('A2', 12345);
        $sheet->setCellValue('B2', 'TORREZ');
        $sheet->setCellValue('C2', 'MARIA');

        $sheet->getStyle('A2')
            ->getNumberFormat()
            ->setFormatCode('00000000');

        $path = $this->saveSpreadsheet($spreadsheet);

        $rows = $this->readRows($path);

        $this->assertCount(1, $rows);
        $this->assertSame('00012345', $rows[0]->sisCode());
    }

    public function test_falla_si_el_archivo_no_es_un_xlsx_valido(): void
    {
        $path = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . uniqid('sciem_invalid_', true)
            . '.xlsx';

        file_put_contents(
            $path,
            'este contenido no es un archivo XLSX'
        );

        $this->temporaryFiles[] = $path;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'No se pudo procesar el archivo XLSX.'
        );

        (new XlsxStudentRosterReader())->read($path);
    }

    /**
     * @return array<int, \App\Services\Academic\Importers\StudentRosterRow>
     */
    private function readRows(string $path): array
    {
        $rows = (new XlsxStudentRosterReader())->read($path);

        if (is_array($rows)) {
            return array_values($rows);
        }

        return iterator_to_array($rows, false);
    }

    private function saveSpreadsheet(
        Spreadsheet $spreadsheet
    ): string {
        $path = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . uniqid('sciem_roster_', true)
            . '.xlsx';

        (new Xlsx($spreadsheet))->save($path);

        $spreadsheet->disconnectWorksheets();

        $this->temporaryFiles[] = $path;

        return $path;
    }
}