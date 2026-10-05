<?php

namespace Tests\Feature\Academic;

use App\Models\Exam;
use App\Models\Group;
use App\Models\Student;
use App\Services\Academic\Importers\StudentRosterDatabaseMatch;
use App\Services\Academic\Importers\StudentRosterPreviewResult;
use App\Services\Exams\ExamParticipantService;
use App\Support\RecordStatus;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsExamCatalog;
use Tests\TestCase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class StudentRosterHttpTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsExamCatalog;

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

    public function test_rechaza_preview_sin_archivo(): void
    {
        $this->seedAcademicCatalog();

        $group = $this->ownActiveGroup();

        $response = $this->postJson(
            '/api/grupos/' . $group->id_grupo . '/nomina/preview',
            []
        );

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'archivo',
            ]);
    }

    public function test_rechaza_archivo_con_extension_no_permitida(): void
    {
        $this->seedAcademicCatalog();

        $group = $this->ownActiveGroup();

        $file = UploadedFile::fake()->create(
            'nomina.pdf',
            10,
            'application/pdf'
        );

        $response = $this->post(
            '/api/grupos/' . $group->id_grupo . '/nomina/preview',
            [
                'archivo' => $file,
            ],
            [
                'Accept' => 'application/json',
            ]
        );

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'archivo',
            ]);
    }

    public function test_rechaza_valor_que_no_es_archivo(): void
    {
        $this->seedAcademicCatalog();

        $group = $this->ownActiveGroup();

        $response = $this->postJson(
            '/api/grupos/' . $group->id_grupo . '/nomina/preview',
            [
                'archivo' => 'esto-no-es-un-archivo',
            ]
        );

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'archivo',
            ]);
    }

    public function test_rechaza_token_con_formato_invalido(): void
    {
        $this->seedAcademicCatalog();

        $group = $this->ownActiveGroup();

        $response = $this->postJson(
            '/api/grupos/' . $group->id_grupo . '/nomina/confirm',
            [
                'token' => 'token-invalido',
            ]
        );

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'token',
            ]);
    }

    public function test_genera_preview_desde_archivo_xlsx_real(): void
    {
        $this->seedAcademicCatalog();

        $group = $this->ownActiveGroup();

        $spreadsheet = new Spreadsheet();

        $sheet = $spreadsheet->getActiveSheet();

        $sheet->fromArray([
            [
                'Estudiante',
                'Apellidos',
                'Nombres',
                '1er Parcial',
            ],
            [
                '00123456',
                'MAMANI QUISPE',
                'LUIS ALBERTO',
                80,
            ],
            [
                '20260020',
                'ROJAS FLORES',
                'MARIA',
                90,
            ],
        ]);

        $path = tempnam(
            sys_get_temp_dir(),
            'sciem_xlsx_'
        );

        if ($path === false) {
            $this->fail(
                'No fue posible crear el archivo XLSX temporal.'
            );
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($path);

        $spreadsheet->disconnectWorksheets();

        $file = new UploadedFile(
            $path,
            'nomina.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        try {
            $response = $this->post(
                '/api/grupos/' . $group->id_grupo . '/nomina/preview',
                [
                    'archivo' => $file,
                ],
                [
                    'Accept' => 'application/json',
                ]
            );
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }

        $response->assertOk();

        $response->assertJson([
            'data' => [
                'total_filas' => 2,
                'filas_validas' => 2,
                'filas_inconsistentes' => 0,
                'filas' => [
                    [
                        'numero_fila' => 2,
                        'codigo_sis' => '00123456',
                        'apellidos' => 'MAMANI QUISPE',
                        'nombres' => 'LUIS ALBERTO',
                        'estado' => StudentRosterDatabaseMatch::NEW_STUDENT,
                        'errores' => [],
                    ],
                    [
                        'numero_fila' => 3,
                        'codigo_sis' => '20260020',
                        'apellidos' => 'ROJAS FLORES',
                        'nombres' => 'MARIA',
                        'estado' => StudentRosterDatabaseMatch::NEW_STUDENT,
                        'errores' => [],
                    ],
                ],
            ],
        ]);

        $token = $response->json(
            'data.token'
        );

        $this->assertIsString($token);

        $this->assertMatchesRegularExpression(
            '/^[a-f0-9]{64}$/',
            $token
        );

        /*
        * El preview XLSX tampoco debe persistir estudiantes.
        */
        $this->assertDatabaseMissing('estudiante', [
            'cod_sis' => '00123456',
        ]);

        $this->assertDatabaseMissing('estudiante', [
            'cod_sis' => '20260020',
        ]);
    }

    public function test_rechaza_xlsx_corrupto_con_error_controlado(): void
    {
        $this->seedAcademicCatalog();

        $group = $this->ownActiveGroup();

        $file = UploadedFile::fake()->createWithContent(
            'nomina.xlsx',
            'esto no es un archivo XLSX valido'
        );

        $response = $this->post(
            '/api/grupos/' . $group->id_grupo . '/nomina/preview',
            [
                'archivo' => $file,
            ],
            [
                'Accept' => 'application/json',
            ]
        );

        $response
            ->assertStatus(422)
            ->assertJson([
                'message' => 'No se pudo procesar el archivo XLSX.',
            ]);
    }

    public function test_rechaza_csv_sin_columnas_requeridas(): void
    {
        $this->seedAcademicCatalog();

        $group = $this->ownActiveGroup();

        $csv = implode(PHP_EOL, [
            'Estudiante,Nombres',
            '20260030,ANA MARIA',
        ]);

        $file = UploadedFile::fake()->createWithContent(
            'nomina.csv',
            $csv
        );

        $response = $this->post(
            '/api/grupos/' . $group->id_grupo . '/nomina/preview',
            [
                'archivo' => $file,
            ],
            [
                'Accept' => 'application/json',
            ]
        );

        $response
            ->assertStatus(422)
            ->assertJson([
                'message' => 'Falta la columna requerida: apellidos.',
            ]);
    }

    public function test_rechaza_preview_de_grupo_de_otro_docente(): void
    {
        $this->seedAcademicCatalog();

        $group = Group::query()
            ->where(
                'id_usuario_docente',
                $this->otroDocenteId
            )
            ->where(
                'id_periodo',
                $this->periodoActivoId
            )
            ->where(
                'estado',
                RecordStatus::ACTIVE
            )
            ->firstOrFail();

        $response = $this->post(
            '/api/grupos/' . $group->id_grupo . '/nomina/preview',
            [
                'archivo' => $this->validRosterCsv(),
            ],
            [
                'Accept' => 'application/json',
            ]
        );

        $response->assertStatus(403);
    }

    public function test_rechaza_preview_de_grupo_inactivo(): void
    {
        $this->seedAcademicCatalog();

        $activeGroup = $this->ownActiveGroup();

        $group = Group::create([
            'id_carrera' => $activeGroup->id_carrera,
            'id_materia' => $activeGroup->id_materia,
            'num_grupo' => '99',
            'gestion' => $activeGroup->gestion,
            'estado' => RecordStatus::INACTIVE,
            'id_usuario_docente' => $this->docenteId,
            'id_periodo' => $this->periodoActivoId,
        ]);

        $response = $this->post(
            '/api/grupos/' . $group->id_grupo . '/nomina/preview',
            [
                'archivo' => $this->validRosterCsv(),
            ],
            [
                'Accept' => 'application/json',
            ]
        );

        $response->assertStatus(422);
    }

    public function test_rechaza_preview_de_grupo_de_periodo_anterior(): void
    {
        $this->seedAcademicCatalog();

        $group = Group::query()
            ->where(
                'id_usuario_docente',
                $this->docenteId
            )
            ->where(
                'id_periodo',
                '!=',
                $this->periodoActivoId
            )
            ->where(
                'estado',
                RecordStatus::ACTIVE
            )
            ->firstOrFail();

        $response = $this->post(
            '/api/grupos/' . $group->id_grupo . '/nomina/preview',
            [
                'archivo' => $this->validRosterCsv(),
            ],
            [
                'Accept' => 'application/json',
            ]
        );

        $response->assertStatus(422);
    }

    public function test_rechaza_preview_de_grupo_inexistente(): void
    {
        $this->seedAcademicCatalog();

        $response = $this->post(
            '/api/grupos/999999999/nomina/preview',
            [
                'archivo' => $this->validRosterCsv(),
            ],
            [
                'Accept' => 'application/json',
            ]
        );

        $response->assertStatus(404);
    }

    public function test_reenviar_la_misma_nomina_no_duplica_inscripciones_por_http(): void
    {
        $this->seedAcademicCatalog();

        $group = $this->ownActiveGroup();

        $students = [];

        foreach ([['20261001', '14000001', 'ANA', 'PEREZ'], ['20261002', '14000002', 'LUIS', 'ROJAS']] as $index => $data) {
            $students[$index] = Student::create([
                'cod_sis' => $data[0],
                'ci' => $data[1],
                'nombre' => $data[2],
                'apellido_paterno' => $data[3],
                'apellido_materno' => null,
                'correo_institucional' => null,
                'telefono' => null,
                'estado' => RecordStatus::ACTIVE,
            ]);

            DB::table('grupo_estudiante')->insert([
                'id_grupo' => $group->id_grupo,
                'id_estudiante' => $students[$index]->id_estudiante,
                'fecha_inscripcion' => '2026-08-0' . ($index + 1),
                'estado' => RecordStatus::ACTIVE,
            ]);
        }

        $previewResponse = $this->previewCsv($group->id_grupo, implode(PHP_EOL, [
            'Estudiante,Apellidos,Nombres',
            '20261001,PEREZ,ANA',
            '20261002,ROJAS,LUIS',
        ]));

        $previewResponse
            ->assertOk()
            ->assertJson([
                'data' => [
                    'total_filas' => 2,
                    'filas_validas' => 2,
                    'filas_inconsistentes' => 0,
                    'filas' => [
                        [
                            'codigo_sis' => '20261001',
                            'estado' => StudentRosterDatabaseMatch::ALREADY_ENROLLED,
                        ],
                        [
                            'codigo_sis' => '20261002',
                            'estado' => StudentRosterDatabaseMatch::ALREADY_ENROLLED,
                        ],
                    ],
                ],
            ]);

        $confirmationResponse = $this->postJson(
            '/api/grupos/' . $group->id_grupo . '/nomina/confirm',
            ['token' => $previewResponse->json('data.token')]
        );

        $confirmationResponse
            ->assertOk()
            ->assertJson([
                'data' => [
                    'total_filas' => 2,
                    'filas_inconsistentes' => 0,
                    'estudiantes_creados' => 0,
                    'estudiantes_inscritos' => 0,
                    'ya_inscritos' => 2,
                ],
            ]);

        $this->assertArrayNotHasKey(
            'inscripciones_inactivas',
            $confirmationResponse->json('data')
        );

        $this->assertSame(
            2,
            DB::table('grupo_estudiante')->where('id_grupo', $group->id_grupo)->count()
        );

        $this->assertDatabaseHas('grupo_estudiante', [
            'id_grupo' => $group->id_grupo,
            'id_estudiante' => $students[0]->id_estudiante,
            'fecha_inscripcion' => '2026-08-01',
            'estado' => RecordStatus::ACTIVE,
        ]);

        $this->assertSame(1, Student::query()->where('cod_sis', '20261001')->count());
        $this->assertSame(1, Student::query()->where('cod_sis', '20261002')->count());
    }

    public function test_el_preview_no_ofrece_ningun_estado_de_inscripcion_inactiva(): void
    {
        $this->seedAcademicCatalog();

        $group = $this->ownActiveGroup();

        $response = $this->previewCsv($group->id_grupo, implode(PHP_EOL, [
            'Estudiante,Apellidos,Nombres',
            '20261001,PEREZ,ANA',
        ]));

        $response->assertOk();

        $this->assertNotContains(
            'inactive_enrollment',
            array_column($response->json('data.filas'), 'estado')
        );
    }

    public function test_los_estudiantes_creados_desde_la_nomina_quedan_sin_ci(): void
    {
        $this->seedAcademicCatalog();

        $group = $this->ownActiveGroup();

        $previewResponse = $this->previewCsv($group->id_grupo, implode(PHP_EOL, [
            'Estudiante,Apellidos,Nombres',
            '20261001,PEREZ ROJAS,ANA',
            '20261002,VARGAS PINTO,LUIS',
        ]));

        $this->postJson(
            '/api/grupos/' . $group->id_grupo . '/nomina/confirm',
            ['token' => $previewResponse->json('data.token')]
        )->assertOk()->assertJsonPath('data.estudiantes_creados', 2);

        $this->assertSame(
            2,
            Student::query()->whereIn('cod_sis', ['20261001', '20261002'])->whereNull('ci')->count()
        );
    }

    public function test_reporta_los_codigos_sis_invalidos_con_su_numero_de_fila(): void
    {
        $this->seedAcademicCatalog();

        $group = $this->ownActiveGroup();

        $response = $this->previewCsv($group->id_grupo, implode(PHP_EOL, [
            'Estudiante,Apellidos,Nombres',
            '20261001,PEREZ,ANA',
            'A1234567,ROJAS,LUIS',
            '1234567,VARGAS,MARIA',
            '1234567890123,PINTO,JOSE',
        ]));

        $response
            ->assertOk()
            ->assertJsonPath('data.filas_validas', 1)
            ->assertJsonPath('data.filas_inconsistentes', 3)
            ->assertJsonPath('data.filas.1.numero_fila', 3)
            ->assertJsonPath('data.filas.1.estado', StudentRosterPreviewResult::INCONSISTENT)
            ->assertJsonPath('data.filas.1.errores', ['sis_code_not_numeric'])
            ->assertJsonPath('data.filas.2.numero_fila', 4)
            ->assertJsonPath('data.filas.2.errores', ['sis_code_invalid_length'])
            ->assertJsonPath('data.filas.3.numero_fila', 5)
            ->assertJsonPath('data.filas.3.errores', ['sis_code_invalid_length']);
    }

    public function test_importa_una_vez_los_duplicados_identicos_y_reporta_las_filas_extra(): void
    {
        $this->seedAcademicCatalog();

        $group = $this->ownActiveGroup();

        $previewResponse = $this->previewCsv($group->id_grupo, implode(PHP_EOL, [
            'Estudiante,Apellidos,Nombres',
            '20261001,PEREZ,ANA',
            '20261002,ROJAS,LUIS',
            '20261001,PEREZ,ANA',
        ]));

        $previewResponse
            ->assertOk()
            ->assertJsonPath('data.filas_validas', 2)
            ->assertJsonPath('data.filas_inconsistentes', 1)
            ->assertJsonPath('data.filas.0.errores', [])
            ->assertJsonPath('data.filas.2.numero_fila', 4)
            ->assertJsonPath('data.filas.2.errores', ['duplicate_row_in_file']);

        $this->postJson(
            '/api/grupos/' . $group->id_grupo . '/nomina/confirm',
            ['token' => $previewResponse->json('data.token')]
        )
            ->assertOk()
            ->assertJsonPath('data.estudiantes_creados', 2)
            ->assertJsonPath('data.filas_inconsistentes', 1);

        $this->assertSame(1, Student::query()->where('cod_sis', '20261001')->count());
        $this->assertSame(
            2,
            DB::table('grupo_estudiante')->where('id_grupo', $group->id_grupo)->count()
        );
    }

    public function test_no_importa_ninguna_de_las_filas_duplicadas_que_difieren(): void
    {
        $this->seedAcademicCatalog();

        $group = $this->ownActiveGroup();

        $previewResponse = $this->previewCsv($group->id_grupo, implode(PHP_EOL, [
            'Estudiante,Apellidos,Nombres',
            '20261001,PEREZ,ANA',
            '20261002,ROJAS,LUIS',
            '20261001,PEREZ,ANA MARIA',
        ]));

        $previewResponse
            ->assertOk()
            ->assertJsonPath('data.filas_validas', 1)
            ->assertJsonPath('data.filas_inconsistentes', 2)
            ->assertJsonPath('data.filas.0.errores', ['conflicting_duplicate_in_file'])
            ->assertJsonPath('data.filas.2.errores', ['conflicting_duplicate_in_file']);

        $this->postJson(
            '/api/grupos/' . $group->id_grupo . '/nomina/confirm',
            ['token' => $previewResponse->json('data.token')]
        )->assertOk()->assertJsonPath('data.estudiantes_creados', 1);

        $this->assertDatabaseMissing('estudiante', ['cod_sis' => '20261001']);
        $this->assertDatabaseHas('estudiante', ['cod_sis' => '20261002']);
    }

    /**
     * @dataProvider lockingExamStates
     */
    public function test_rechaza_el_preview_si_un_examen_del_grupo_esta_en_ingreso_o_en_curso(string $state): void
    {
        $this->seedExamCatalog();

        $group = $this->ownActiveGroup();
        $this->linkExam($group, $state);

        $response = $this->previewCsv($group->id_grupo, implode(PHP_EOL, [
            'Estudiante,Apellidos,Nombres',
            '20261001,PEREZ,ANA',
        ]));

        $response
            ->assertStatus(422)
            ->assertJsonPath(
                'message',
                'La nómina no puede modificarse mientras un examen del grupo está en ingreso o en curso.'
            );
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function lockingExamStates(): array
    {
        return [
            'EN_INGRESO' => [Exam::EN_INGRESO],
            'EN_CURSO' => [Exam::EN_CURSO],
        ];
    }

    public function test_rechaza_la_confirmacion_si_el_examen_pasa_a_ingreso_tras_el_preview(): void
    {
        $this->seedExamCatalog();

        $group = $this->ownActiveGroup();
        $exam = $this->linkExam($group, Exam::PROGRAMADO);

        $previewResponse = $this->previewCsv($group->id_grupo, implode(PHP_EOL, [
            'Estudiante,Apellidos,Nombres',
            '20261001,PEREZ,ANA',
        ]))->assertOk();

        $exam->estado = Exam::EN_INGRESO;
        $exam->save();

        $this->postJson(
            '/api/grupos/' . $group->id_grupo . '/nomina/confirm',
            ['token' => $previewResponse->json('data.token')]
        )->assertStatus(422);

        $this->assertDatabaseMissing('estudiante', ['cod_sis' => '20261001']);
    }

    public function test_la_nomina_cambia_libremente_mientras_el_examen_esta_programado(): void
    {
        $this->seedExamCatalog();

        $group = $this->ownActiveGroup();
        $exam = $this->linkExam($group, Exam::PROGRAMADO);

        $before = app(ExamParticipantService::class)->counts($exam->id_examen);

        $previewResponse = $this->previewCsv($group->id_grupo, implode(PHP_EOL, [
            'Estudiante,Apellidos,Nombres',
            '20261001,PEREZ,ANA',
            '20261002,ROJAS,LUIS',
        ]))->assertOk();

        $this->postJson(
            '/api/grupos/' . $group->id_grupo . '/nomina/confirm',
            ['token' => $previewResponse->json('data.token')]
        )->assertOk();

        $after = app(ExamParticipantService::class)->counts($exam->id_examen);

        $this->assertSame($before['esperados'] + 2, $after['esperados']);
        $this->assertSame(0, $after['ingresados']);
    }

    private function previewCsv(int $groupId, string $csv)
    {
        return $this->post(
            '/api/grupos/' . $groupId . '/nomina/preview',
            ['archivo' => UploadedFile::fake()->createWithContent('nomina.csv', $csv)],
            ['Accept' => 'application/json']
        );
    }

    private function linkExam(Group $group, string $state): Exam
    {
        $exam = $this->createExam(['estado' => $state]);

        DB::table('grupo_examen')->insert([
            'id_examen' => $exam->id_examen,
            'id_grupo' => $group->id_grupo,
        ]);

        return $exam;
    }

    public function test_rechaza_nomina_sin_estudiantes(): void
    {
        $this->seedAcademicCatalog();

        $group = $this->ownActiveGroup();

        $csv = 'Estudiante,Apellidos,Nombres';

        $file = UploadedFile::fake()->createWithContent(
            'nomina.csv',
            $csv
        );

        $response = $this->post(
            '/api/grupos/' . $group->id_grupo . '/nomina/preview',
            [
                'archivo' => $file,
            ],
            [
                'Accept' => 'application/json',
            ]
        );

        $response
            ->assertStatus(422)
            ->assertJson([
                'message' => 'La nómina no contiene estudiantes.',
            ]);
    }

    public function test_rechaza_archivo_que_supera_limite_de_10_mb(): void
    {
        $this->seedAcademicCatalog();

        $group = $this->ownActiveGroup();

        $file = UploadedFile::fake()->create(
            'nomina.csv',
            10241,
            'text/csv'
        );

        $response = $this->post(
            '/api/grupos/' . $group->id_grupo . '/nomina/preview',
            [
                'archivo' => $file,
            ],
            [
                'Accept' => 'application/json',
            ]
        );

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'archivo',
            ]);
    }

    private function validRosterCsv(): UploadedFile
    {
        $csv = implode(PHP_EOL, [
            'Estudiante,Apellidos,Nombres',
            '20269999,PEREZ ROJAS,ANA MARIA',
        ]);

        return UploadedFile::fake()->createWithContent(
            'nomina.csv',
            $csv
        );
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