<?php

namespace Tests\Feature\Academic;

use App\Models\Group;
use App\Models\Student;
use App\Services\Academic\Importers\StudentRosterDatabaseMatch;
use App\Services\Academic\Importers\StudentRosterPreviewResult;
use App\Support\RecordStatus;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsAcademicCatalog;
use Tests\TestCase;

class StudentRosterHttpTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsAcademicCatalog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(
            CacheRepository::class,
            new Repository(new ArrayStore())
        );
    }

    public function test_genera_preview_y_confirma_nomina_por_http(): void
    {
        $this->seedAcademicCatalog();

        $group = $this->ownActiveGroup();

        $existingStudent = Student::create([
            'cod_sis' => '20260002',
            'ci' => '13000001',
            'nombre' => 'CARLOS',
            'apellido_paterno' => 'LOPEZ',
            'apellido_materno' => null,
            'correo_institucional' => null,
            'telefono' => null,
            'estado' => RecordStatus::ACTIVE,
        ]);

        $csv = implode(PHP_EOL, [
            'Estudiante,Apellidos,Nombres,1er Parcial',
            '20260001,PEREZ ROJAS,ANA MARIA,80',
            '20260002,LOPEZ,CARLOS,75',
            '20260003,,MARIA,90',
        ]);

        $file = UploadedFile::fake()->createWithContent(
            'nomina.csv',
            $csv
        );

        $previewResponse = $this->post(
            '/api/grupos/' . $group->id_grupo . '/nomina/preview',
            [
                'archivo' => $file,
            ],
            [
                'Accept' => 'application/json',
            ]
        );

        $previewResponse->assertOk();

        $previewResponse->assertJson([
            'data' => [
                'total_filas' => 3,
                'filas_validas' => 2,
                'filas_inconsistentes' => 1,
                'filas' => [
                    [
                        'numero_fila' => 2,
                        'codigo_sis' => '20260001',
                        'apellidos' => 'PEREZ ROJAS',
                        'nombres' => 'ANA MARIA',
                        'estado' => StudentRosterDatabaseMatch::NEW_STUDENT,
                        'errores' => [],
                    ],
                    [
                        'numero_fila' => 3,
                        'codigo_sis' => '20260002',
                        'apellidos' => 'LOPEZ',
                        'nombres' => 'CARLOS',
                        'estado' => StudentRosterDatabaseMatch::EXISTING_STUDENT,
                        'errores' => [],
                    ],
                    [
                        'numero_fila' => 4,
                        'codigo_sis' => '20260003',
                        'apellidos' => null,
                        'nombres' => 'MARIA',
                        'estado' => StudentRosterPreviewResult::INCONSISTENT,
                        'errores' => [
                            'missing_last_names',
                        ],
                    ],
                ],
            ],
        ]);

        $token = $previewResponse->json(
            'data.token'
        );

        $this->assertIsString($token);

        $this->assertMatchesRegularExpression(
            '/^[a-f0-9]{64}$/',
            $token
        );

        /*
         * El preview todavía no debe persistir nada.
         */
        $this->assertDatabaseMissing('estudiante', [
            'cod_sis' => '20260001',
        ]);

        $this->assertDatabaseMissing('estudiante', [
            'cod_sis' => '20260003',
        ]);

        $this->assertDatabaseMissing('grupo_estudiante', [
            'id_grupo' => $group->id_grupo,
            'id_estudiante' => $existingStudent->id_estudiante,
        ]);

        /*
         * Confirmación utilizando solamente el token.
         */
        $confirmationResponse = $this->postJson(
            '/api/grupos/' . $group->id_grupo . '/nomina/confirm',
            [
                'token' => $token,
            ]
        );

        $confirmationResponse->assertOk();

        $confirmationResponse->assertJson([
            'data' => [
                'total_filas' => 3,
                'filas_inconsistentes' => 1,
                'estudiantes_creados' => 1,
                'estudiantes_inscritos' => 2,
                'ya_inscritos' => 0,
                'inscripciones_inactivas' => 0,
            ],
        ]);

        /*
         * El estudiante nuevo ahora sí debe existir.
         */
        $newStudent = Student::query()
            ->where(
                'cod_sis',
                '20260001'
            )
            ->firstOrFail();

        $this->assertSame(
            'ANA MARIA',
            $newStudent->nombre
        );

        $this->assertSame(
            'PEREZ ROJAS',
            $newStudent->apellido_paterno
        );

        /*
         * Tanto el estudiante nuevo como el ya existente
         * deben quedar inscritos en el grupo.
         */
        $this->assertDatabaseHas('grupo_estudiante', [
            'id_grupo' => $group->id_grupo,
            'id_estudiante' => $newStudent->id_estudiante,
            'estado' => RecordStatus::ACTIVE,
        ]);

        $this->assertDatabaseHas('grupo_estudiante', [
            'id_grupo' => $group->id_grupo,
            'id_estudiante' => $existingStudent->id_estudiante,
            'estado' => RecordStatus::ACTIVE,
        ]);

        /*
         * La fila inconsistente jamás debe incorporarse.
         */
        $this->assertDatabaseMissing('estudiante', [
            'cod_sis' => '20260003',
        ]);

        /*
         * Una confirmación exitosa consume el token.
         */
        $secondConfirmationResponse = $this->postJson(
            '/api/grupos/' . $group->id_grupo . '/nomina/confirm',
            [
                'token' => $token,
            ]
        );

        $secondConfirmationResponse
            ->assertStatus(404)
            ->assertJson([
                'message' => 'El preview de la nómina no existe o ha expirado.',
            ]);
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
}