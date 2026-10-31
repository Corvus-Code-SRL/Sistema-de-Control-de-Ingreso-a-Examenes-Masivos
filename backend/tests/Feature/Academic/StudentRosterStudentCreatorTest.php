<?php

namespace Tests\Feature\Academic;

use App\Services\Academic\Importers\StudentRosterRow;
use App\Services\Academic\Importers\StudentRosterStudentCreator;
use App\Services\Academic\Importers\StudentRosterStudentMapper;
use App\Support\RecordStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StudentRosterStudentCreatorTest extends TestCase
{
    use DatabaseTransactions;

    private function creator(): StudentRosterStudentCreator
    {
        return new StudentRosterStudentCreator(new StudentRosterStudentMapper());
    }

    public function test_crea_estudiante_nuevo_desde_fila_de_nomina_sin_ci(): void
    {
        $row = new StudentRosterRow(
            8,
            '20200240',
            'ACUÑA QUISPE',
            'JOSE DIEGO'
        );

        $created = $this->creator()->createMany([$row]);

        $this->assertSame(1, $created);
        $this->assertDatabaseHas('estudiante', [
            'cod_sis' => '20200240',
            'ci' => null,
            'nombre' => 'JOSE DIEGO',
            'apellido_paterno' => 'ACUÑA QUISPE',
            'apellido_materno' => null,
            'correo_institucional' => null,
            'telefono' => null,
            'estado' => RecordStatus::ACTIVE,
        ]);
    }

    public function test_crea_todos_los_estudiantes_con_una_sola_consulta_y_sin_consultas_de_ci(): void
    {
        $rows = [];

        for ($i = 1; $i <= 50; $i++) {
            $rows[] = new StudentRosterRow($i + 1, (string) (300000000 + $i), 'PEREZ ROJAS', 'ANA ' . $i);
        }

        $before = DB::table('estudiante')->count();

        DB::enableQueryLog();
        DB::flushQueryLog();

        $created = $this->creator()->createMany($rows);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertSame(50, $created);
        $this->assertSame($before + 50, DB::table('estudiante')->count());
        $this->assertCount(1, $queries);
    }

    public function test_permite_varios_estudiantes_sin_ci(): void
    {
        $this->creator()->createMany([
            new StudentRosterRow(2, '202400001', 'PEREZ ROJAS', 'ANA'),
            new StudentRosterRow(3, '202400002', 'ROJAS FLORES', 'LUIS'),
        ]);

        $this->assertSame(
            2,
            DB::table('estudiante')->whereIn('cod_sis', ['202400001', '202400002'])->whereNull('ci')->count()
        );
    }

    public function test_un_cod_sis_que_ya_existe_se_reutiliza_sin_fallar_ni_duplicar(): void
    {
        DB::table('estudiante')->insert([
            'cod_sis' => '202400001',
            'ci' => '1234567',
            'nombre' => 'PREVIO',
            'apellido_paterno' => 'EXISTENTE',
            'estado' => RecordStatus::ACTIVE,
        ]);

        $created = $this->creator()->createMany([
            new StudentRosterRow(2, '202400001', 'PEREZ ROJAS', 'ANA'),
            new StudentRosterRow(3, '202400002', 'ROJAS FLORES', 'LUIS'),
        ]);

        $this->assertSame(1, $created);
        $this->assertSame(1, DB::table('estudiante')->where('cod_sis', '202400001')->count());
        $this->assertSame('PREVIO', DB::table('estudiante')->where('cod_sis', '202400001')->value('nombre'));
        $this->assertSame('1234567', DB::table('estudiante')->where('cod_sis', '202400001')->value('ci'));
    }

    public function test_sin_filas_no_ejecuta_ninguna_consulta(): void
    {
        DB::enableQueryLog();
        DB::flushQueryLog();

        $created = $this->creator()->createMany([]);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertSame(0, $created);
        $this->assertCount(0, $queries);
    }
}
