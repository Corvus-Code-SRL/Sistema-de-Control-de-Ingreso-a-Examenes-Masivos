<?php

namespace Tests\Unit\Services\Academic\Importers;

use App\Services\Academic\Importers\CsvStudentRosterReader;
use App\Services\Academic\Importers\StudentRosterReaderResolver;
use App\Services\Academic\Importers\XlsxStudentRosterReader;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class StudentRosterReaderResolverTest extends TestCase
{
    public function test_resuelve_lector_csv(): void
    {
        $csvReader = new CsvStudentRosterReader();
        $xlsxReader = new XlsxStudentRosterReader();

        $resolver = new StudentRosterReaderResolver(
            $csvReader,
            $xlsxReader
        );

        $this->assertSame(
            $csvReader,
            $resolver->resolve('csv')
        );
    }

    public function test_resuelve_lector_xlsx(): void
    {
        $csvReader = new CsvStudentRosterReader();
        $xlsxReader = new XlsxStudentRosterReader();

        $resolver = new StudentRosterReaderResolver(
            $csvReader,
            $xlsxReader
        );

        $this->assertSame(
            $xlsxReader,
            $resolver->resolve('xlsx')
        );
    }

    public function test_normaliza_mayusculas_y_espacios(): void
    {
        $resolver = new StudentRosterReaderResolver(
            new CsvStudentRosterReader(),
            new XlsxStudentRosterReader()
        );

        $this->assertInstanceOf(
            XlsxStudentRosterReader::class,
            $resolver->resolve(' XLSX ')
        );
    }

    public function test_rechaza_formato_no_soportado(): void
    {
        $resolver = new StudentRosterReaderResolver(
            new CsvStudentRosterReader(),
            new XlsxStudentRosterReader()
        );

        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Formato de nómina no soportado.'
        );

        $resolver->resolve('xls');
    }
}