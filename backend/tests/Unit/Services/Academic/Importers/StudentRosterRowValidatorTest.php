<?php

namespace Tests\Unit\Services\Academic\Importers;

use App\Services\Academic\Importers\StudentRosterRow;
use App\Services\Academic\Importers\StudentRosterRowValidator;
use PHPUnit\Framework\TestCase;

class StudentRosterRowValidatorTest extends TestCase
{
    public function testItAcceptsCompleteValidRow(): void
    {
        $row = new StudentRosterRow(
            8,
            '20200240',
            'ACUÑA QUISPE',
            'JOSE DIEGO'
        );

        $errors = (new StudentRosterRowValidator())->validate($row);

        $this->assertSame([], $errors);
    }

    public function testItDetectsAllMissingRequiredValues(): void
    {
        $row = new StudentRosterRow(
            12,
            null,
            null,
            null
        );

        $errors = (new StudentRosterRowValidator())->validate($row);

        $this->assertSame([
            'missing_sis_code',
            'missing_last_names',
            'missing_first_names',
        ], $errors);
    }

    public function testItRejectsSisCodeLongerThanDatabaseLimit(): void
    {
        $row = new StudentRosterRow(
            5,
            '1234567890123456',
            'PEREZ ROJAS',
            'ANA'
        );

        $errors = (new StudentRosterRowValidator())->validate($row);

        $this->assertSame([
            'sis_code_too_long',
        ], $errors);
    }

    public function testItRejectsFirstNamesLongerThanDatabaseLimit(): void
    {
        $row = new StudentRosterRow(
            6,
            '20200240',
            'PEREZ ROJAS',
            str_repeat('A', 51)
        );

        $errors = (new StudentRosterRowValidator())->validate($row);

        $this->assertSame([
            'first_names_too_long',
        ], $errors);
    }

    public function testItAcceptsValuesAtDatabaseLengthLimits(): void
    {
        $row = new StudentRosterRow(
            7,
            '123456789012345',
            'PEREZ ROJAS',
            str_repeat('A', 50)
        );

        $errors = (new StudentRosterRowValidator())->validate($row);

        $this->assertSame([], $errors);
    }
}