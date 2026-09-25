<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseSchemaTest extends TestCase
{
    use DatabaseTransactions;

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

    public function testStudentCiIsNullable(): void
    {
        $column = DB::selectOne(
            "select is_nullable from information_schema.columns
             where table_schema = 'public' and table_name = 'estudiante' and column_name = 'ci'"
        );

        $this->assertSame('YES', $column->is_nullable);
    }

    public function testStudentCiUniquenessToleratesSeveralNullsButRejectsRepeatedValues(): void
    {
        $this->insertStudent('202400001', null);
        $this->insertStudent('202400002', null);

        $this->assertSame(2, DB::table('estudiante')->whereNull('ci')->count());

        $this->insertStudent('202400003', '1234567');

        $this->expectException(QueryException::class);
        $this->insertStudent('202400004', '1234567');
    }

    private function insertStudent(string $sisCode, ?string $ci): void
    {
        DB::table('estudiante')->insert([
            'cod_sis' => $sisCode,
            'ci' => $ci,
            'nombre' => 'Nombre',
            'apellido_paterno' => 'Apellido',
            'estado' => 'ACTIVO',
        ]);
    }
}