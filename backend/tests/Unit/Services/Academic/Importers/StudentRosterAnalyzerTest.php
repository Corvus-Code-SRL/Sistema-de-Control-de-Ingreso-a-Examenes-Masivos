<?php

namespace Tests\Unit\Services\Academic\Importers;

use App\Services\Academic\Importers\CsvStudentRosterReader;
use App\Services\Academic\Importers\StudentRosterAnalyzer;
use App\Services\Academic\Importers\StudentRosterRow;
use App\Services\Academic\Importers\StudentRosterRowValidator;
use PHPUnit\Framework\TestCase;

class StudentRosterAnalyzerTest extends TestCase
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

    public function testItCountsValidRows(): void
    {
        $rows = [
            new StudentRosterRow(
                2,
                '20200240',
                'ACUÑA QUISPE',
                'JOSE DIEGO'
            ),
            new StudentRosterRow(
                3,
                '20210567',
                'PEREZ ROJAS',
                'ANA MARIA'
            ),
        ];

        $result = $this->analyzer()->analyze($rows);

        $this->assertSame(2, $result->totalRows());
        $this->assertSame(2, $result->validRows());
        $this->assertSame(0, $result->inconsistentRows());
    }

    public function testItKeepsRowValidationErrors(): void
    {
        $rows = [
            new StudentRosterRow(
                2,
                '20200240',
                null,
                'JOSE DIEGO'
            ),
        ];

        $result = $this->analyzer()->analyze($rows);

        $this->assertSame(1, $result->totalRows());
        $this->assertSame(0, $result->validRows());
        $this->assertSame(1, $result->inconsistentRows());

        $this->assertSame(
            ['missing_last_names'],
            $result->rows()[0]->errors()
        );

        $this->assertFalse(
            $result->rows()[0]->isValid()
        );
    }

    public function testItMarksEveryDuplicateSisCodeOccurrence(): void
    {
        $rows = [
            new StudentRosterRow(
                2,
                '20200240',
                'ACUÑA QUISPE',
                'JOSE DIEGO'
            ),
            new StudentRosterRow(
                3,
                '20210567',
                'PEREZ ROJAS',
                'ANA MARIA'
            ),
            new StudentRosterRow(
                4,
                '20200240',
                'ACUÑA QUISPE',
                'JOSE DIEGO'
            ),
        ];

        $result = $this->analyzer()->analyze($rows);

        $this->assertSame(3, $result->totalRows());
        $this->assertSame(1, $result->validRows());
        $this->assertSame(2, $result->inconsistentRows());

        $this->assertSame(
            ['duplicate_sis_code_in_file'],
            $result->rows()[0]->errors()
        );

        $this->assertSame(
            [],
            $result->rows()[1]->errors()
        );

        $this->assertSame(
            ['duplicate_sis_code_in_file'],
            $result->rows()[2]->errors()
        );
    }

    public function testItCombinesValidationAndDuplicateErrors(): void
    {
        $rows = [
            new StudentRosterRow(
                2,
                '20200240',
                null,
                'JOSE DIEGO'
            ),
            new StudentRosterRow(
                3,
                '20200240',
                'ACUÑA QUISPE',
                'JOSE DIEGO'
            ),
        ];

        $result = $this->analyzer()->analyze($rows);

        $this->assertSame([
            'missing_last_names',
            'duplicate_sis_code_in_file',
        ], $result->rows()[0]->errors());

        $this->assertSame([
            'duplicate_sis_code_in_file',
        ], $result->rows()[1]->errors());

        $this->assertSame(0, $result->validRows());
        $this->assertSame(2, $result->inconsistentRows());
    }

    public function testItAnalyzesRowsDirectlyFromCsvReader(): void
    {
        $path = $this->createCsv(
            "Estudiante,Apellidos,Nombres\n"
            . "20200240,ACUÑA QUISPE,JOSE DIEGO\n"
            . "20210567,PEREZ ROJAS,ANA MARIA\n"
            . "20200240,ACUÑA QUISPE,JOSE DIEGO\n"
        );

        $reader = new CsvStudentRosterReader();

        $result = $this->analyzer()->analyze(
            $reader->read($path)
        );

        $this->assertSame(3, $result->totalRows());
        $this->assertSame(1, $result->validRows());
        $this->assertSame(2, $result->inconsistentRows());

        $this->assertSame(
            2,
            $result->rows()[0]->row()->rowNumber()
        );

        $this->assertSame(
            4,
            $result->rows()[2]->row()->rowNumber()
        );
    }

    private function analyzer(): StudentRosterAnalyzer
    {
        return new StudentRosterAnalyzer(
            new StudentRosterRowValidator()
        );
    }

    private function createCsv(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'sciem_roster_');

        if ($path === false) {
            $this->fail(
                'No se pudo crear el archivo temporal.'
            );
        }

        file_put_contents($path, $content);

        $this->temporaryFiles[] = $path;

        return $path;
    }
}