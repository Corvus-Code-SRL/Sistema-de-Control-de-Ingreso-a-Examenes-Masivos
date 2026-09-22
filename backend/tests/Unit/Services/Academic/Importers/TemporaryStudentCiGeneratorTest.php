<?php

namespace Tests\Unit\Services\Academic\Importers;

use App\Services\Academic\Importers\TemporaryStudentCiGenerator;
use PHPUnit\Framework\TestCase;

class TemporaryStudentCiGeneratorTest extends TestCase
{
    public function testItGeneratesTemporaryCiWithExpectedFormat(): void
    {
        $ci = (new TemporaryStudentCiGenerator())->generate();

        $this->assertSame(10, strlen($ci));
        $this->assertStringStartsWith('TMP', $ci);
        $this->assertMatchesRegularExpression(
            '/^TMP[A-Z0-9]{7}$/',
            $ci
        );
    }

    public function testGeneratedValuesAlwaysRespectDatabaseFormat(): void
    {
        $generator = new TemporaryStudentCiGenerator();

        for ($i = 0; $i < 100; $i++) {
            $ci = $generator->generate();

            $this->assertSame(10, strlen($ci));
            $this->assertMatchesRegularExpression(
                '/^TMP[A-Z0-9]{7}$/',
                $ci
            );
        }
    }
}