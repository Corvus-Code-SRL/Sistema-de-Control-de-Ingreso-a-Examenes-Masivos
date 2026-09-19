<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseSchemaTest extends TestCase
{
    public function testItUsesOnlyTestDatabase(): void
    {
        $this->assertSame(
            'sciem_test',
            DB::connection()->getDatabaseName()
        );
    }

    public function testItLoadsRequiredAcademicTables(): void
    {
        $this->assertTrue(Schema::hasTable('estudiante'));
        $this->assertTrue(Schema::hasTable('grupo'));
        $this->assertTrue(Schema::hasTable('grupo_estudiante'));
    }
}