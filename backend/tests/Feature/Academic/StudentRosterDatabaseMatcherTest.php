<?php

namespace Tests\Feature\Academic;

use App\Models\Group;
use App\Models\Student;
use App\Services\Academic\Importers\StudentRosterAnalyzer;
use App\Services\Academic\Importers\StudentRosterDatabaseMatch;
use App\Services\Academic\Importers\StudentRosterDatabaseMatcher;
use App\Services\Academic\Importers\StudentRosterRow;
use App\Services\Academic\Importers\StudentRosterRowValidator;
use App\Support\RecordStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsAcademicCatalog;
use Tests\TestCase;

class StudentRosterDatabaseMatcherTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsAcademicCatalog;

    public function test_clasifica_estudiantes_nuevos_existentes_y_ya_inscritos(): void
    {
        $this->seedAcademicCatalog();

        $groupId = $this->ownGroupId();

        $existingStudent = $this->createStudent(
            '20200240',
            '10000001'
        );

        $enrolledStudent = $this->createStudent(
            '20210567',
            '10000002'
        );

        DB::table('grupo_estudiante')->insert([
            'id_grupo' => $groupId,
            'id_estudiante' => $enrolledStudent->id_estudiante,
            'fecha_inscripcion' => '2026-09-19',
            'estado' => RecordStatus::ACTIVE,
        ]);

        $analysis = $this->analyze([
            new StudentRosterRow(
                2,
                '20200240',
                'ACUÑA QUISPE',
                'JOSE DIEGO'
            ),
            new StudentRosterRow(
                3,
                '20210567',
                'PEREZ ROJAS',
                'ANA MARIA'
            ),
            new StudentRosterRow(
                4,
                '20230001',
                'ROJAS FLORES',
                'CARLOS'
            ),
        ]);

        $matches = (new StudentRosterDatabaseMatcher())
            ->classify($groupId, $analysis);

        $existing = $this->findMatch($matches, '20200240');
        $enrolled = $this->findMatch($matches, '20210567');
        $new = $this->findMatch($matches, '20230001');

        $this->assertSame(
            StudentRosterDatabaseMatch::EXISTING_STUDENT,
            $existing->status()
        );

        $this->assertSame(
            $existingStudent->id_estudiante,
            $existing->studentId()
        );

        $this->assertSame(
            StudentRosterDatabaseMatch::ALREADY_ENROLLED,
            $enrolled->status()
        );

        $this->assertSame(
            $enrolledStudent->id_estudiante,
            $enrolled->studentId()
        );

        $this->assertSame(
            StudentRosterDatabaseMatch::NEW_STUDENT,
            $new->status()
        );

        $this->assertNull($new->studentId());
    }

    public function test_ignora_filas_inconsistentes_en_la_comparacion(): void
    {
        $this->seedAcademicCatalog();

        $analysis = $this->analyze([
            new StudentRosterRow(
                2,
                '20200240',
                null,
                'JOSE DIEGO'
            ),
            new StudentRosterRow(
                3,
                '20210567',
                'PEREZ ROJAS',
                'ANA MARIA'
            ),
        ]);

        $matches = (new StudentRosterDatabaseMatcher())
            ->classify($this->ownGroupId(), $analysis);

        $this->assertCount(1, $matches);

        $this->assertSame(
            '20210567',
            $matches[0]->rowAnalysis()->row()->sisCode()
        );
    }

    public function test_no_consulta_la_base_si_no_hay_filas_validas(): void
    {
        $this->seedAcademicCatalog();

        $groupId = $this->ownGroupId();

        $analysis = $this->analyze([
            new StudentRosterRow(
                2,
                null,
                null,
                null
            ),
        ]);

        DB::enableQueryLog();
        DB::flushQueryLog();

        $matches = (new StudentRosterDatabaseMatcher())
            ->classify($groupId, $analysis);

        $queries = DB::getQueryLog();

        DB::disableQueryLog();

        $this->assertSame([], $matches);
        $this->assertCount(0, $queries);
    }

    public function test_no_agrega_una_consulta_por_cada_estudiante(): void
    {
        $this->seedAcademicCatalog();

        $groupId = $this->ownGroupId();

        $firstStudent = $this->createStudent(
            '20200001',
            '11000001'
        );

        $firstAnalysis = $this->analyze([
            new StudentRosterRow(
                2,
                $firstStudent->cod_sis,
                'PEREZ ROJAS',
                'ANA'
            ),
        ]);

        DB::enableQueryLog();
        DB::flushQueryLog();

        (new StudentRosterDatabaseMatcher())
            ->classify($groupId, $firstAnalysis);

        $initialQueries = count(DB::getQueryLog());

        $rows = [
            new StudentRosterRow(
                2,
                $firstStudent->cod_sis,
                'PEREZ ROJAS',
                'ANA'
            ),
        ];

        for ($i = 2; $i <= 21; $i++) {
            $sisCode = '2020' . str_pad(
                (string) $i,
                4,
                '0',
                STR_PAD_LEFT
            );

            $ci = '11' . str_pad(
                (string) $i,
                6,
                '0',
                STR_PAD_LEFT
            );

            $student = $this->createStudent(
                $sisCode,
                $ci
            );

            $rows[] = new StudentRosterRow(
                $i + 1,
                $student->cod_sis,
                'PEREZ ROJAS',
                'ANA'
            );
        }

        $largeAnalysis = $this->analyze($rows);

        DB::flushQueryLog();

        (new StudentRosterDatabaseMatcher())
            ->classify($groupId, $largeAnalysis);

        $queriesWithMoreStudents = count(DB::getQueryLog());

        DB::disableQueryLog();

        $this->assertSame(
            $initialQueries,
            $queriesWithMoreStudents
        );
    }

    /**
     * @param array<int, StudentRosterRow> $rows
     */
    private function analyze(array $rows)
    {
        return (new StudentRosterAnalyzer(
            new StudentRosterRowValidator()
        ))->analyze($rows);
    }

    private function ownGroupId(): int
    {
        return (int) Group::query()
            ->where('id_carrera', $this->sistemasId)
            ->where('id_materia', $this->calculoId)
            ->where('num_grupo', '1')
            ->where('id_usuario_docente', $this->docenteId)
            ->where('id_periodo', $this->periodoActivoId)
            ->value('id_grupo');
    }

    private function createStudent(
        string $sisCode,
        string $ci
    ): Student {
        return Student::create([
            'cod_sis' => $sisCode,
            'ci' => $ci,
            'nombre' => 'Nombre',
            'apellido_paterno' => 'Apellido',
            'apellido_materno' => null,
            'correo_institucional' => null,
            'telefono' => null,
            'estado' => RecordStatus::ACTIVE,
        ]);
    }

    /**
     * @param array<int, StudentRosterDatabaseMatch> $matches
     */
    private function findMatch(
        array $matches,
        string $sisCode
    ): StudentRosterDatabaseMatch {
        foreach ($matches as $match) {
            if (
                $match->rowAnalysis()->row()->sisCode()
                === $sisCode
            ) {
                return $match;
            }
        }

        $this->fail(
            "No se encontró el Código SIS {$sisCode}."
        );
    }
}