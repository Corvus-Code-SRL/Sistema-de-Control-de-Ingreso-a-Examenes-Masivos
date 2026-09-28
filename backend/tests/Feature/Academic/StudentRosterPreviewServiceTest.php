<?php

namespace Tests\Feature\Academic;

use App\Models\Group;
use App\Models\Student;
use App\Services\Academic\Importers\CsvStudentRosterReader;
use App\Services\Academic\Importers\StudentRosterAnalyzer;
use App\Services\Academic\Importers\StudentRosterDatabaseMatch;
use App\Services\Academic\Importers\StudentRosterDatabaseMatcher;
use App\Services\Academic\Importers\StudentRosterPreviewResult;
use App\Services\Academic\Importers\StudentRosterPreviewService;
use App\Services\Academic\Importers\StudentRosterPreviewStore;
use App\Services\Academic\Importers\StudentRosterReaderResolver;
use App\Services\Academic\Importers\StudentRosterRowValidator;
use App\Services\Academic\Importers\XlsxStudentRosterReader;
use App\Services\Academic\StudentRosterGroupAccess;
use App\Services\Exams\ExamRosterLockService;
use App\Support\RecordStatus;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsAcademicCatalog;
use Tests\TestCase;

class StudentRosterPreviewServiceTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsAcademicCatalog;

    private ?string $temporaryFile = null;

    protected function tearDown(): void
    {
        if (
            $this->temporaryFile !== null
            && is_file($this->temporaryFile)
        ) {
            unlink($this->temporaryFile);
        }

        parent::tearDown();
    }

    public function test_genera_preview_completo_sin_modificar_base_de_datos(): void
    {
        $this->seedAcademicCatalog();

        $group = $this->ownActiveGroup();

        $existingStudent = $this->createStudent(
            '20200240',
            '10000001'
        );

        $alreadyEnrolledStudent = $this->createStudent(
            '20210567',
            '10000002'
        );

        $legacyRowStudent = $this->createStudent(
            '20230456',
            '10000003'
        );

        DB::table('grupo_estudiante')->insert([
            [
                'id_grupo' => $group->id_grupo,
                'id_estudiante' => $alreadyEnrolledStudent->id_estudiante,
                'fecha_inscripcion' => '2026-08-01',
                'estado' => RecordStatus::ACTIVE,
            ],
            [
                'id_grupo' => $group->id_grupo,
                'id_estudiante' => $legacyRowStudent->id_estudiante,
                'fecha_inscripcion' => '2026-08-15',
                'estado' => RecordStatus::INACTIVE,
            ],
        ]);

        $studentCountBefore = DB::table('estudiante')->count();

        $enrollmentCountBefore = DB::table('grupo_estudiante')
            ->where('id_grupo', $group->id_grupo)
            ->count();

        $this->temporaryFile = $this->createCsv();

        [$service, $previewStore] = $this->previewService();

        $result = $service->generate(
            (int) $group->id_grupo,
            $this->temporaryFile,
            'csv'
        );

        $this->assertSame(5, $result->totalRows());
        $this->assertSame(4, $result->validRows());
        $this->assertSame(1, $result->inconsistentRows());

        $this->assertMatchesRegularExpression(
            '/^[a-f0-9]{64}$/',
            $result->token()
        );

        $rows = $result->rows();

        $this->assertCount(5, $rows);

        $this->assertSame(
            StudentRosterDatabaseMatch::NEW_STUDENT,
            $rows[0]['status']
        );

        $this->assertSame(
            StudentRosterDatabaseMatch::EXISTING_STUDENT,
            $rows[1]['status']
        );

        $this->assertSame(
            StudentRosterDatabaseMatch::ALREADY_ENROLLED,
            $rows[2]['status']
        );

        // Una fila de inscripción es una fila de nómina, sea cual sea su estado.
        $this->assertSame(
            StudentRosterDatabaseMatch::ALREADY_ENROLLED,
            $rows[3]['status']
        );

        $this->assertSame(
            StudentRosterPreviewResult::INCONSISTENT,
            $rows[4]['status']
        );

        $this->assertSame(
            ['missing_last_names'],
            $rows[4]['errors']
        );

        $this->assertSame(
            '20239999',
            $rows[4]['sis_code']
        );

        $preview = $previewStore->find(
            $result->token()
        );

        $this->assertNotNull($preview);

        $this->assertSame(
            $group->id_grupo,
            $preview->groupId()
        );

        $this->assertSame(
            $this->docenteId,
            $preview->teacherId()
        );

        $this->assertCount(
            5,
            $preview->rows()
        );

        $this->assertSame(
            '20230001',
            $preview->rows()[0]->sisCode()
        );

        $this->assertNull(
            $preview->rows()[4]->lastNames()
        );

        $this->assertSame(
            $studentCountBefore,
            DB::table('estudiante')->count()
        );

        $this->assertSame(
            $enrollmentCountBefore,
            DB::table('grupo_estudiante')
                ->where(
                    'id_grupo',
                    $group->id_grupo
                )
                ->count()
        );

        $this->assertDatabaseMissing('estudiante', [
            'cod_sis' => '20230001',
        ]);

        $existingStudent->refresh();

        $this->assertSame(
            'NOMBRE REGISTRADO',
            $existingStudent->nombre
        );
    }

    /**
     * @return array{
     *     0: StudentRosterPreviewService,
     *     1: StudentRosterPreviewStore
     * }
     */
    private function previewService(): array
    {
        $previewStore = new StudentRosterPreviewStore(
            new Repository(new ArrayStore())
        );

        $service = new StudentRosterPreviewService(
            new StudentRosterGroupAccess(new ExamRosterLockService()),
            new StudentRosterReaderResolver(
                new CsvStudentRosterReader(),
                new XlsxStudentRosterReader()
            ),
            new StudentRosterAnalyzer(
                new StudentRosterRowValidator()
            ),
            new StudentRosterDatabaseMatcher(),
            $previewStore
        );

        return [
            $service,
            $previewStore,
        ];
    }

    private function ownActiveGroup(): Group
    {
        return Group::query()
            ->where(
                'id_usuario_docente',
                $this->docenteId
            )
            ->where(
                'id_periodo',
                $this->periodoActivoId
            )
            ->where(
                'estado',
                RecordStatus::ACTIVE
            )
            ->where(
                'num_grupo',
                '1'
            )
            ->firstOrFail();
    }

    private function createStudent(
        string $sisCode,
        string $ci
    ): Student {
        return Student::create([
            'cod_sis' => $sisCode,
            'ci' => $ci,
            'nombre' => 'NOMBRE REGISTRADO',
            'apellido_paterno' => 'APELLIDO',
            'apellido_materno' => null,
            'correo_institucional' => null,
            'telefono' => null,
            'estado' => RecordStatus::ACTIVE,
        ]);
    }

    private function createCsv(): string
    {
        $path = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . uniqid('sciem_preview_', true)
            . '.csv';

        $content = implode(PHP_EOL, [
            'Estudiante,Apellidos,Nombres,1er Parcial',
            '20230001,ROJAS FLORES,CARLOS,80',
            '20200240,ACUÑA QUISPE,JOSE DIEGO,75',
            '20210567,PEREZ ROJAS,ANA MARIA,90',
            '20230456,VARGAS FLORES,MARIA,70',
            '20239999,,ESTUDIANTE INCOMPLETO,65',
        ]);

        file_put_contents(
            $path,
            $content
        );

        return $path;
    }
}