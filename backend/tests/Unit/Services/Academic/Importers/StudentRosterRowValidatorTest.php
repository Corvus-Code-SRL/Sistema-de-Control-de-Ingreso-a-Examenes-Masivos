<?php

namespace Tests\Unit\Services\Academic\Importers;

use App\Services\Academic\Importers\StudentRosterRow;
use App\Services\Academic\Importers\StudentRosterRowValidator;
use PHPUnit\Framework\TestCase;

/**
 * La regla del código SIS es solo de estudiantes de nómina: solo dígitos, de 8 a 12.
 */
class StudentRosterRowValidatorTest extends TestCase
{
    private function validator(): StudentRosterRowValidator
    {
        return new StudentRosterRowValidator(8, 12);
    }

    public function testItAcceptsCompleteValidRow(): void
    {
        $row = new StudentRosterRow(
            8,
            '20200240',
            'ACUÑA QUISPE',
            'JOSE DIEGO'
        );

        $this->assertSame([], $this->validator()->validate($row));
    }

    public function testItDetectsAllMissingRequiredValues(): void
    {
        $row = new StudentRosterRow(
            12,
            null,
            null,
            null
        );

        $this->assertSame([
            'missing_sis_code',
            'missing_last_names',
            'missing_first_names',
        ], $this->validator()->validate($row));
    }

    /**
     * @dataProvider validSisCodes
     */
    public function testItAcceptsSisCodesOfEightToTwelveDigits(string $sisCode): void
    {
        $row = new StudentRosterRow(2, $sisCode, 'PEREZ ROJAS', 'ANA');

        $this->assertSame([], $this->validator()->validate($row));
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function validSisCodes(): array
    {
        return [
            'ocho dígitos (antes del 2000)' => ['99123456'],
            'nueve dígitos (hoy)' => ['202400001'],
            'doce dígitos (cota máxima)' => ['123456789012'],
            'conserva ceros iniciales' => ['000123456'],
        ];
    }

    /**
     * @dataProvider sisCodesOutOfRange
     */
    public function testItRejectsSisCodesOutOfTheLengthRange(string $sisCode): void
    {
        $row = new StudentRosterRow(2, $sisCode, 'PEREZ ROJAS', 'ANA');

        $this->assertSame(
            ['sis_code_invalid_length'],
            $this->validator()->validate($row)
        );
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function sisCodesOutOfRange(): array
    {
        return [
            'siete dígitos' => ['1234567'],
            'trece dígitos' => ['1234567890123'],
            'un dígito' => ['1'],
        ];
    }

    /**
     * @dataProvider nonNumericSisCodes
     */
    public function testItRejectsSisCodesWithNonDigitCharacters(string $sisCode): void
    {
        $row = new StudentRosterRow(2, $sisCode, 'PEREZ ROJAS', 'ANA');

        $this->assertContains('sis_code_not_numeric', $this->validator()->validate($row));
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function nonNumericSisCodes(): array
    {
        return [
            'letras' => ['ABCDEFGHI'],
            'código de docente alfanumérico' => ['A1234567'],
            'guion' => ['2024-0001'],
            'espacio interno' => ['2024 0001'],
            'decimal de Excel' => ['20240000.5'],
        ];
    }

    public function testItUsesTheBoundsItIsGiven(): void
    {
        $row = new StudentRosterRow(2, '1234567', 'PEREZ ROJAS', 'ANA');

        $this->assertSame([], (new StudentRosterRowValidator(5, 9))->validate($row));
        $this->assertSame(
            ['sis_code_invalid_length'],
            (new StudentRosterRowValidator(8, 12))->validate($row)
        );
    }

    public function testItRejectsFirstNamesLongerThanDatabaseLimit(): void
    {
        $row = new StudentRosterRow(
            6,
            '20200240',
            'PEREZ ROJAS',
            str_repeat('A', 51)
        );

        $this->assertSame(['first_names_too_long'], $this->validator()->validate($row));
    }

    public function testItAcceptsFirstNamesAtDatabaseLimit(): void
    {
        $row = new StudentRosterRow(
            7,
            '202400001',
            'PEREZ ROJAS',
            str_repeat('A', 50)
        );

        $this->assertSame([], $this->validator()->validate($row));
    }

    public function testItRejectsLastNamesLongerThanDatabaseLimit(): void
    {
        $row = new StudentRosterRow(
            8,
            '20200240',
            str_repeat('A', 31),
            'JOSE DIEGO'
        );

        $this->assertSame(['last_names_too_long'], $this->validator()->validate($row));
    }

    public function testItAcceptsLastNamesAtDatabaseLimit(): void
    {
        $row = new StudentRosterRow(
            9,
            '20200240',
            str_repeat('A', 30),
            'JOSE DIEGO'
        );

        $this->assertSame([], $this->validator()->validate($row));
    }
}
