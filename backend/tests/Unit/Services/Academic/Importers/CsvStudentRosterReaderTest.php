<?php

namespace Tests\Unit\Services\Academic\Importers;

use App\Services\Academic\Importers\CsvStudentRosterReader;
use App\Services\Academic\Importers\StudentRosterRow;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class CsvStudentRosterReaderTest extends TestCase
{
    /**
     * @var array<int, string>
     */
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    public function testItReadsRequiredColumnsAndIgnoresGrades(): void
    {
        $path = $this->createCsv(
            "#,Estudiante,Apellidos,Nombres,1er Parcial,Nota Final\n"
            . "1,20200240,ACUÑA QUISPE,JOSE DIEGO,80,90\n"
            . "2,20210567,PEREZ ROJAS,ANA MARIA,75,85\n"
        );

        $rows = iterator_to_array(
            (new CsvStudentRosterReader())->read($path)
        );

        $this->assertCount(2, $rows);

        $this->assertRosterRow(
            $rows[0],
            2,
            '20200240',
            'ACUÑA QUISPE',
            'JOSE DIEGO'
        );

        $this->assertRosterRow(
            $rows[1],
            3,
            '20210567',
            'PEREZ ROJAS',
            'ANA MARIA'
        );
    }

    public function testItReadsSemicolonSeparatedCsv(): void
    {
        $path = $this->createCsv(
            "Estudiante;Apellidos;Nombres\n"
            . "20200240;ACUÑA QUISPE;JOSE DIEGO\n"
        );

        $rows = iterator_to_array(
            (new CsvStudentRosterReader())->read($path)
        );

        $this->assertCount(1, $rows);

        $this->assertRosterRow(
            $rows[0],
            2,
            '20200240',
            'ACUÑA QUISPE',
            'JOSE DIEGO'
        );
    }

    public function testItPreservesSisCodeLeadingZeros(): void
    {
        $path = $this->createCsv(
            "Estudiante,Apellidos,Nombres\n"
            . "00123456,PEREZ ROJAS,ANA\n"
        );

        $rows = iterator_to_array(
            (new CsvStudentRosterReader())->read($path)
        );

        $this->assertSame('00123456', $rows[0]->sisCode());
    }

    public function testItSkipsCompletelyEmptyRows(): void
    {
        $path = $this->createCsv(
            "Estudiante,Apellidos,Nombres\n"
            . "20200240,ACUÑA QUISPE,JOSE DIEGO\n"
            . ",,\n"
            . "20210567,PEREZ ROJAS,ANA MARIA\n"
        );

        $rows = iterator_to_array(
            (new CsvStudentRosterReader())->read($path)
        );

        $this->assertCount(2, $rows);

        $this->assertSame(2, $rows[0]->rowNumber());
        $this->assertSame(4, $rows[1]->rowNumber());
    }

    public function testItKeepsMissingCellAsNullForLaterValidation(): void
    {
        $path = $this->createCsv(
            "Estudiante,Apellidos,Nombres\n"
            . "20200240,,JOSE DIEGO\n"
        );

        $rows = iterator_to_array(
            (new CsvStudentRosterReader())->read($path)
        );

        $this->assertCount(1, $rows);
        $this->assertSame('20200240', $rows[0]->sisCode());
        $this->assertNull($rows[0]->lastNames());
        $this->assertSame('JOSE DIEGO', $rows[0]->firstNames());
    }

    public function testItAcceptsUtf8BomInFirstHeader(): void
    {
        $path = $this->createCsv(
            "\xEF\xBB\xBFEstudiante,Apellidos,Nombres\n"
            . "20200240,ACUÑA QUISPE,JOSE DIEGO\n"
        );

        $rows = iterator_to_array(
            (new CsvStudentRosterReader())->read($path)
        );

        $this->assertCount(1, $rows);
        $this->assertSame('20200240', $rows[0]->sisCode());
    }

    public function testItRejectsCsvWithoutRequiredHeader(): void
    {
        $path = $this->createCsv(
            "Estudiante,Apellidos\n"
            . "20200240,ACUÑA QUISPE\n"
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Falta la columna requerida: nombres.'
        );

        iterator_to_array(
            (new CsvStudentRosterReader())->read($path)
        );
    }

    private function createCsv(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'sciem_csv_');

        if ($path === false) {
            $this->fail('No se pudo crear el archivo temporal.');
        }

        file_put_contents($path, $content);

        $this->temporaryFiles[] = $path;

        return $path;
    }

    private function assertRosterRow(
        StudentRosterRow $row,
        int $rowNumber,
        ?string $sisCode,
        ?string $lastNames,
        ?string $firstNames
    ): void {
        $this->assertSame($rowNumber, $row->rowNumber());
        $this->assertSame($sisCode, $row->sisCode());
        $this->assertSame($lastNames, $row->lastNames());
        $this->assertSame($firstNames, $row->firstNames());
    }
}