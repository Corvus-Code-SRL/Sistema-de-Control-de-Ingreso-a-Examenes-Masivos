<?php

namespace Tests\Unit\Services\Academic\Importers;

use App\Services\Academic\Importers\StudentRosterRow;
use App\Services\Academic\Importers\StudentRosterStudentMapper;
use App\Support\RecordStatus;
use PHPUnit\Framework\TestCase;

class StudentRosterStudentMapperTest extends TestCase
{
    public function testItMapsRosterRowToStudentAttributesWithoutCi(): void
    {
        $row = new StudentRosterRow(
            8,
            '20200240',
            'ACUÑA QUISPE',
            'JOSE DIEGO'
        );

        $attributes = (new StudentRosterStudentMapper())->map($row);

        $this->assertSame([
            'cod_sis' => '20200240',
            'ci' => null,
            'nombre' => 'JOSE DIEGO',
            'apellido_paterno' => 'ACUÑA QUISPE',
            'apellido_materno' => null,
            'correo_institucional' => null,
            'telefono' => null,
            'estado' => RecordStatus::ACTIVE,
        ], $attributes);
    }

    public function testItNeverInventsACi(): void
    {
        $row = new StudentRosterRow(9, '00123456', 'PEREZ ROJAS', 'ANA');

        $attributes = (new StudentRosterStudentMapper())->map($row);

        $this->assertNull($attributes['ci']);
    }

    public function testItPreservesSisCodeLeadingZeros(): void
    {
        $row = new StudentRosterRow(9, '00123456', 'PEREZ ROJAS', 'ANA');

        $attributes = (new StudentRosterStudentMapper())->map($row);

        $this->assertSame('00123456', $attributes['cod_sis']);
    }

    public function testItKeepsCombinedLastNamesWithoutSplittingThem(): void
    {
        $row = new StudentRosterRow(
            10,
            '20230001',
            'DE LA FUENTE PEREZ',
            'CARLOS'
        );

        $attributes = (new StudentRosterStudentMapper())->map($row);

        $this->assertSame('DE LA FUENTE PEREZ', $attributes['apellido_paterno']);
        $this->assertNull($attributes['apellido_materno']);
    }
}
