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

        $student = $this->creator()->create($row);

        $this->assertNotNull($student->id_estudiante);
        $this->assertSame('20200240', $student->cod_sis);
        $this->assertSame('JOSE DIEGO', $student->nombre);
        $this->assertSame('ACUÑA QUISPE', $student->apellido_paterno);
        $this->assertNull($student->apellido_materno);
        $this->assertNull($student->correo_institucional);
        $this->assertNull($student->telefono);
        $this->assertNull($student->ci);
        $this->assertSame(RecordStatus::ACTIVE, $student->estado);

        $this->assertDatabaseHas('estudiante', [
            'id_estudiante' => $student->id_estudiante,
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

    public function test_crea_solamente_un_estudiante_y_sin_consultas_de_ci(): void
    {
        $row = new StudentRosterRow(9, '00123456', 'PEREZ ROJAS', 'ANA');

        $before = DB::table('estudiante')->count();

        DB::enableQueryLog();
        DB::flushQueryLog();

        $this->creator()->create($row);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertSame($before + 1, DB::table('estudiante')->count());
        $this->assertCount(1, $queries);
    }

    public function test_permite_varios_estudiantes_sin_ci(): void
    {
        $this->creator()->create(new StudentRosterRow(2, '202400001', 'PEREZ ROJAS', 'ANA'));
        $this->creator()->create(new StudentRosterRow(3, '202400002', 'ROJAS FLORES', 'LUIS'));

        $this->assertSame(
            2,
            DB::table('estudiante')->whereIn('cod_sis', ['202400001', '202400002'])->whereNull('ci')->count()
        );
    }
}
