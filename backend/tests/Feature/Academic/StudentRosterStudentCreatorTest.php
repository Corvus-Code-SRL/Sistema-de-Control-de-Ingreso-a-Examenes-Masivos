<?php

namespace Tests\Feature\Academic;

use App\Services\Academic\Importers\StudentRosterRow;
use App\Services\Academic\Importers\StudentRosterStudentCreator;
use App\Services\Academic\Importers\StudentRosterStudentMapper;
use App\Services\Academic\Importers\TemporaryStudentCiGenerator;
use App\Support\RecordStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StudentRosterStudentCreatorTest extends TestCase
{
    use DatabaseTransactions;

    public function test_crea_estudiante_nuevo_desde_fila_de_nomina(): void
    {
        $row = new StudentRosterRow(
            8,
            '20200240',
            'ACUÑA QUISPE',
            'JOSE DIEGO'
        );

        $creator = new StudentRosterStudentCreator(
            new TemporaryStudentCiGenerator(),
            new StudentRosterStudentMapper()
        );

        $student = $creator->create($row);

        $this->assertNotNull($student->id_estudiante);
        $this->assertSame('20200240', $student->cod_sis);
        $this->assertSame('JOSE DIEGO', $student->nombre);
        $this->assertSame(
            'ACUÑA QUISPE',
            $student->apellido_paterno
        );
        $this->assertNull($student->apellido_materno);
        $this->assertNull($student->correo_institucional);
        $this->assertNull($student->telefono);
        $this->assertSame(
            RecordStatus::ACTIVE,
            $student->estado
        );

        $this->assertMatchesRegularExpression(
            '/^TMP[A-Z0-9]{7}$/',
            $student->ci
        );

        $this->assertDatabaseHas('estudiante', [
            'id_estudiante' => $student->id_estudiante,
            'cod_sis' => '20200240',
            'ci' => $student->ci,
            'nombre' => 'JOSE DIEGO',
            'apellido_paterno' => 'ACUÑA QUISPE',
            'apellido_materno' => null,
            'correo_institucional' => null,
            'telefono' => null,
            'estado' => RecordStatus::ACTIVE,
        ]);
    }

    public function test_crea_solamente_un_estudiante(): void
    {
        $row = new StudentRosterRow(
            9,
            '00123456',
            'PEREZ ROJAS',
            'ANA'
        );

        $before = DB::table('estudiante')->count();

        $creator = new StudentRosterStudentCreator(
            new TemporaryStudentCiGenerator(),
            new StudentRosterStudentMapper()
        );

        $creator->create($row);

        $after = DB::table('estudiante')->count();

        $this->assertSame(
            $before + 1,
            $after
        );
    }
}