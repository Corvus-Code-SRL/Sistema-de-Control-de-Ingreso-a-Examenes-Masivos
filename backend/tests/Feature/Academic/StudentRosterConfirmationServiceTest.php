<?php

namespace Tests\Feature\Academic;

use App\Exceptions\Academic\StudentRosterPreviewUnavailableException;
use App\Models\Group;
use App\Models\Student;
use App\Services\Academic\Importers\StudentRosterAnalyzer;
use App\Services\Academic\Importers\StudentRosterConfirmationService;
use App\Services\Academic\Importers\StudentRosterConfirmer;
use App\Services\Academic\Importers\StudentRosterDatabaseMatcher;
use App\Services\Academic\Importers\StudentRosterPreviewStore;
use App\Services\Academic\Importers\StudentRosterRow;
use App\Services\Academic\Importers\StudentRosterRowValidator;
use App\Services\Academic\Importers\StudentRosterStudentCreator;
use App\Services\Academic\Importers\StudentRosterStudentMapper;
use App\Services\Academic\Importers\TemporaryStudentCiGenerator;
use App\Services\Academic\StudentRosterGroupAccess;
use App\Support\RecordStatus;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Concerns\SeedsAcademicCatalog;
use Tests\TestCase;

class StudentRosterConfirmationServiceTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsAcademicCatalog;

    public function test_confirma_desde_token_y_reconsulta_estado_actual_de_base(): void
    {
        $this->seedAcademicCatalog();

        $group = $this->ownActiveGroup();

        $store = $this->previewStore();

        $analysis = $this->analyze([
            new StudentRosterRow(
                2,
                '20250001',
                'PEREZ ROJAS',
                'ANA'
            ),
        ]);

        $token = $store->store(
            (int) $group->id_grupo,
            $this->docenteId,
            $analysis
        );

        /*
         * Simula que, después del preview pero antes de confirmar,
         * otro proceso registró e inscribió al estudiante.
         */
        $student = $this->createStudent(
            '20250001',
            '12000001'
        );

        DB::table('grupo_estudiante')->insert([
            'id_grupo' => $group->id_grupo,
            'id_estudiante' => $student->id_estudiante,
            'fecha_inscripcion' => '2026-09-20',
            'estado' => RecordStatus::ACTIVE,
        ]);

        $result = $this->confirmationService(
            $store
        )->confirm($token);

        $this->assertSame(1, $result->totalRows());
        $this->assertSame(0, $result->inconsistentRows());
        $this->assertSame(0, $result->createdStudents());
        $this->assertSame(0, $result->enrolledStudents());
        $this->assertSame(1, $result->alreadyEnrolled());
        $this->assertSame(0, $result->inactiveEnrollments());

        $this->assertSame(
            1,
            Student::query()
                ->where('cod_sis', '20250001')
                ->count()
        );

        $this->assertSame(
            1,
            DB::table('grupo_estudiante')
                ->where('id_grupo', $group->id_grupo)
                ->where(
                    'id_estudiante',
                    $student->id_estudiante
                )
                ->count()
        );

        $this->assertNull(
            $store->find($token)
        );
    }

    public function test_rechaza_token_inexistente_o_expirado(): void
    {
        $this->seedAcademicCatalog();

        $store = $this->previewStore();

        try {
            $this->confirmationService($store)->confirm(
                str_repeat('a', 64)
            );

            $this->fail(
                'La confirmación debía rechazar el token.'
            );
        } catch (
            StudentRosterPreviewUnavailableException $exception
        ) {
            $this->assertSame(
                404,
                $exception->getStatusCode()
            );

            $this->assertSame(
                'El preview de la nómina no existe o ha expirado.',
                $exception->getMessage()
            );
        }
    }

    public function test_rechaza_preview_de_otro_docente_sin_consumir_token(): void
    {
        $this->seedAcademicCatalog();

        $group = $this->ownActiveGroup();

        $store = $this->previewStore();

        $token = $store->store(
            (int) $group->id_grupo,
            $this->otroDocenteId,
            $this->analyze([
                new StudentRosterRow(
                    2,
                    '20250002',
                    'LOPEZ',
                    'MARIA'
                ),
            ])
        );

        try {
            $this->confirmationService($store)->confirm(
                $token
            );

            $this->fail(
                'La confirmación debía rechazar el preview.'
            );
        } catch (
            StudentRosterPreviewUnavailableException $exception
        ) {
            $this->assertSame(
                403,
                $exception->getStatusCode()
            );
        }

        $this->assertNotNull(
            $store->find($token)
        );

        $this->assertDatabaseMissing('estudiante', [
            'cod_sis' => '20250002',
        ]);
    }

    public function test_conserva_token_si_la_confirmacion_falla(): void
    {
        $this->seedAcademicCatalog();

        $group = $this->ownActiveGroup();

        $store = $this->previewStore();

        $token = $store->store(
            (int) $group->id_grupo,
            $this->docenteId,
            $this->analyze([
                new StudentRosterRow(
                    2,
                    '20250003',
                    'VARGAS',
                    'LUIS'
                ),
            ])
        );

        $confirmer = $this->getMockBuilder(
            StudentRosterConfirmer::class
        )
            ->disableOriginalConstructor()
            ->onlyMethods(['confirm'])
            ->getMock();

        $confirmer->expects($this->once())
            ->method('confirm')
            ->willThrowException(
                new RuntimeException(
                    'Fallo técnico simulado.'
                )
            );

        $service = new StudentRosterConfirmationService(
            $store,
            new StudentRosterGroupAccess(),
            new StudentRosterAnalyzer(
                new StudentRosterRowValidator()
            ),
            $confirmer
        );

        try {
            $service->confirm($token);

            $this->fail(
                'La confirmación debía fallar.'
            );
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Fallo técnico simulado.',
                $exception->getMessage()
            );
        }

        $this->assertNotNull(
            $store->find($token)
        );

        $this->assertDatabaseMissing('estudiante', [
            'cod_sis' => '20250003',
        ]);
    }

    private function confirmationService(
        StudentRosterPreviewStore $store
    ): StudentRosterConfirmationService {
        return new StudentRosterConfirmationService(
            $store,
            new StudentRosterGroupAccess(),
            new StudentRosterAnalyzer(
                new StudentRosterRowValidator()
            ),
            new StudentRosterConfirmer(
                new StudentRosterDatabaseMatcher(),
                new StudentRosterStudentCreator(
                    new TemporaryStudentCiGenerator(),
                    new StudentRosterStudentMapper()
                )
            )
        );
    }

    private function previewStore(): StudentRosterPreviewStore
    {
        return new StudentRosterPreviewStore(
            new Repository(new ArrayStore())
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
            'nombre' => 'NOMBRE',
            'apellido_paterno' => 'APELLIDO',
            'apellido_materno' => null,
            'correo_institucional' => null,
            'telefono' => null,
            'estado' => RecordStatus::ACTIVE,
        ]);
    }
}