<?php

namespace Tests\Unit\Services\Academic\Importers;

use App\Services\Academic\Importers\StudentRosterRow;
use App\Services\Academic\Importers\StudentRosterStudentMapper;
use App\Support\RecordStatus;
use PHPUnit\Framework\TestCase;

class StudentRosterStudentMapperTest extends TestCase
{
    public function testItMapsRosterRowToStudentAttributes(): void
    {
        $row = new StudentRosterRow(
            8,
            '20200240',
            'ACUÑA QUISPE',
            'JOSE DIEGO'
        );

        $attributes = (new StudentRosterStudentMapper())->map(
            $row,
            'TMP7K2M91A'
        );

        $this->assertSame([
            'cod_sis' => '20200240',
            'ci' => 'TMP7K2M91A',
            'nombre' => 'JOSE DIEGO',
            'apellido_paterno' => 'ACUÑA QUISPE',
            'apellido_materno' => null,
            'correo_institucional' => null,
            'telefono' => null,
            'estado' => RecordStatus::ACTIVE,
        ], $attributes);
    }

    public function testItPreservesSisCodeLeadingZeros(): void
    {
        $row = new StudentRosterRow(
            9,
            '00123456',
            'PEREZ ROJAS',
            'ANA'
        );

        $attributes = (new StudentRosterStudentMapper())->map(
            $row,
            'TMP0Q8CX4Z'
        );

        $this->assertSame(
            '00123456',
            $attributes['cod_sis']
        );
    }

    public function testItKeepsCombinedLastNamesWithoutSplittingThem(): void
    {
        $row = new StudentRosterRow(
            10,
            '20230001',
            'DE LA FUENTE PEREZ',
            'CARLOS'
        );

        $attributes = (new StudentRosterStudentMapper())->map(
            $row,
            'TMP123ABCD'
        );

        $this->assertSame(
            'DE LA FUENTE PEREZ',
            $attributes['apellido_paterno']
        );

        $this->assertNull(
            $attributes['apellido_materno']
        );
    }

    public function testItUsesProvidedTemporaryCiWithoutGeneratingAnotherOne(): void
    {
        $row = new StudentRosterRow(
            11,
            '20230002',
            'ROJAS FLORES',
            'MARIA'
        );

        $temporaryCi = 'TMPABC1234';

        $attributes = (new StudentRosterStudentMapper())->map(
            $row,
            $temporaryCi
        );

        $this->assertSame(
            $temporaryCi,
            $attributes['ci']
        );
    }
}