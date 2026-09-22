<?php

namespace Tests\Feature\Academic;

use App\Models\Group;
use App\Models\Student;
use App\Services\Academic\Importers\StudentRosterAnalysisResult;
use App\Services\Academic\Importers\StudentRosterAnalyzer;
use App\Services\Academic\Importers\StudentRosterConfirmer;
use App\Services\Academic\Importers\StudentRosterDatabaseMatcher;
use App\Services\Academic\Importers\StudentRosterRow;
use App\Services\Academic\Importers\StudentRosterRowValidator;
use App\Services\Academic\Importers\StudentRosterStudentCreator;
use App\Services\Academic\Importers\StudentRosterStudentMapper;
use App\Services\Academic\Importers\TemporaryStudentCiGenerator;
use App\Support\RecordStatus;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsAcademicCatalog;
use Tests\TestCase;

class StudentRosterConfirmerTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsAcademicCatalog;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_confirma_nuevos_y_existentes_sin_duplicar_inscripciones(): void
    {
        Carbon::setTestNow('2026-09-19 10:00:00');

        $this->seedAcademicCatalog();

        $groupId = $this->ownGroupId('1');
        $otherGroupId = $this->ownGroupId('2');

        $existingStudent = $this->createStudent(
            '20200240',
            '10000001',
            'NOMBRE REGISTRADO'
        );

        $alreadyEnrolledStudent = $this->createStudent(
            '20210567',
            '10000002'
        );

        $inactiveStudent = $this->createStudent(
            '20230456',
            '10000003'
        );

        DB::table('grupo_estudiante')->insert([
            [
                'id_grupo' => $groupId,
                'id_estudiante' => $alreadyEnrolledStudent->id_estudiante,
                'fecha_inscripcion' => '2026-08-01',
                'estado' => RecordStatus::ACTIVE,
            ],
            [
                'id_grupo' => $groupId,
                'id_estudiante' => $inactiveStudent->id_estudiante,
                'fecha_inscripcion' => '2026-08-15',
                'estado' => RecordStatus::INACTIVE,
            ],
        ]);

        $analysis = $this->analyze([
            new StudentRosterRow(
                2,
                '20230001',
                'ROJAS FLORES',
                'CARLOS'
            ),
            new StudentRosterRow(
                3,
                '20200240',
                'ACUÑA QUISPE',
                'JOSE DIEGO'
            ),
            new StudentRosterRow(
                4,
                '20210567',
                'PEREZ ROJAS',
                'ANA MARIA'
            ),
            new StudentRosterRow(
                5,
                '20230456',
                'VARGAS FLORES',
                'MARIA'
            ),
            new StudentRosterRow(
                6,
                '20239999',
                null,
                'ESTUDIANTE INCOMPLETO'
            ),
        ]);

        $result = $this->confirmer()->confirm(
            $groupId,
            $analysis
        );

        $this->assertSame(5, $result->totalRows());
        $this->assertSame(1, $result->inconsistentRows());
        $this->assertSame(1, $result->createdStudents());
        $this->assertSame(2, $result->enrolledStudents());
        $this->assertSame(1, $result->alreadyEnrolled());
        $this->assertSame(1, $result->inactiveEnrollments());

        $newStudent = Student::query()
            ->where('cod_sis', '20230001')
            ->first();

        $this->assertNotNull($newStudent);

        $this->assertDatabaseHas('grupo_estudiante', [
            'id_grupo' => $groupId,
            'id_estudiante' => $newStudent->id_estudiante,
            'fecha_inscripcion' => '2026-09-19',
            'estado' => RecordStatus::ACTIVE,
        ]);

        $this->assertDatabaseHas('grupo_estudiante', [
            'id_grupo' => $groupId,
            'id_estudiante' => $existingStudent->id_estudiante,
            'fecha_inscripcion' => '2026-09-19',
            'estado' => RecordStatus::ACTIVE,
        ]);

        $this->assertDatabaseMissing('grupo_estudiante', [
            'id_grupo' => $otherGroupId,
            'id_estudiante' => $newStudent->id_estudiante,
        ]);

        $this->assertDatabaseMissing('grupo_estudiante', [
            'id_grupo' => $otherGroupId,
            'id_estudiante' => $existingStudent->id_estudiante,
        ]);

        $this->assertSame(
            1,
            DB::table('grupo_estudiante')
                ->where('id_grupo', $groupId)
                ->where(
                    'id_estudiante',
                    $alreadyEnrolledStudent->id_estudiante
                )
                ->count()
        );

        $this->assertDatabaseHas('grupo_estudiante', [
            'id_grupo' => $groupId,
            'id_estudiante' => $alreadyEnrolledStudent->id_estudiante,
            'fecha_inscripcion' => '2026-08-01',
            'estado' => RecordStatus::ACTIVE,
        ]);

        $this->assertDatabaseHas('grupo_estudiante', [
            'id_grupo' => $groupId,
            'id_estudiante' => $inactiveStudent->id_estudiante,
            'fecha_inscripcion' => '2026-08-15',
            'estado' => RecordStatus::INACTIVE,
        ]);

        $existingStudent->refresh();

        $this->assertSame(
            'NOMBRE REGISTRADO',
            $existingStudent->nombre
        );

        $this->assertDatabaseMissing('estudiante', [
            'cod_sis' => '20239999',
        ]);
    }

    public function test_revierte_todo_si_falla_la_confirmacion(): void
    {
        $analysis = $this->analyze([
            new StudentRosterRow(
                2,
                '20240001',
                'PEREZ ROJAS',
                'ANA'
            ),
        ]);

        $nonExistentGroupId = 999999;

        try {
            $this->confirmer()->confirm(
                $nonExistentGroupId,
                $analysis
            );

            $this->fail(
                'La confirmación debía fallar por grupo inexistente.'
            );
        } catch (QueryException $exception) {
            $this->assertDatabaseMissing('estudiante', [
                'cod_sis' => '20240001',
            ]);

            $this->assertDatabaseMissing('grupo_estudiante', [
                'id_grupo' => $nonExistentGroupId,
            ]);
        }
    }

    private function confirmer(): StudentRosterConfirmer
    {
        return new StudentRosterConfirmer(
            new StudentRosterDatabaseMatcher(),
            new StudentRosterStudentCreator(
                new TemporaryStudentCiGenerator(),
                new StudentRosterStudentMapper()
            )
        );
    }

    /**
     * @param array<int, StudentRosterRow> $rows
     */
    private function analyze(
        array $rows
    ): StudentRosterAnalysisResult {
        return (new StudentRosterAnalyzer(
            new StudentRosterRowValidator()
        ))->analyze($rows);
    }

    private function ownGroupId(string $number): int
    {
        return (int) Group::query()
            ->where('id_carrera', $this->sistemasId)
            ->where('id_materia', $this->calculoId)
            ->where('num_grupo', $number)
            ->where(
                'id_usuario_docente',
                $this->docenteId
            )
            ->where(
                'id_periodo',
                $this->periodoActivoId
            )
            ->value('id_grupo');
    }

    private function createStudent(
        string $sisCode,
        string $ci,
        string $name = 'NOMBRE'
    ): Student {
        return Student::create([
            'cod_sis' => $sisCode,
            'ci' => $ci,
            'nombre' => $name,
            'apellido_paterno' => 'APELLIDO',
            'apellido_materno' => null,
            'correo_institucional' => null,
            'telefono' => null,
            'estado' => RecordStatus::ACTIVE,
        ]);
    }
}