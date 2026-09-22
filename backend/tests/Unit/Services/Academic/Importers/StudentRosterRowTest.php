<?php

namespace Tests\Unit\Services\Academic\Importers;

use App\Services\Academic\Importers\StudentRosterRow;
use PHPUnit\Framework\TestCase;

class StudentRosterRowTest extends TestCase
{
    public function testItPreservesRosterRowData(): void
    {
        $row = new StudentRosterRow(
            8,
            '20200240',
            'ACUÑA QUISPE',
            'JOSE DIEGO'
        );

        $this->assertSame(8, $row->rowNumber());
        $this->assertSame('20200240', $row->sisCode());
        $this->assertSame('ACUÑA QUISPE', $row->lastNames());
        $this->assertSame('JOSE DIEGO', $row->firstNames());
    }

    public function testItAllowsMissingValuesForLaterValidation(): void
    {
        $row = new StudentRosterRow(
            12,
            null,
            null,
            null
        );

        $this->assertSame(12, $row->rowNumber());
        $this->assertNull($row->sisCode());
        $this->assertNull($row->lastNames());
        $this->assertNull($row->firstNames());
    }

    public function testItPreservesSisCodeAsString(): void
    {
        $row = new StudentRosterRow(
            5,
            '00123456',
            'PEREZ ROJAS',
            'ANA'
        );

        $this->assertSame('00123456', $row->sisCode());
    }
}