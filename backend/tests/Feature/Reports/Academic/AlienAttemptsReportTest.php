<?php

namespace Tests\Feature\Reports\Academic;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsRiskCenterRecords;
use Tests\Concerns\SeedsSecurityAccounts;
use Tests\TestCase;

/**
 * HU-063: GET /api/reportes/intentos-ajenos, el reporte de estudiantes que intentaron ingresar
 * a un examen que no les corresponde.
 */
class AlienAttemptsReportTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsRiskCenterRecords;
    use SeedsSecurityAccounts;

    private array $context;
    private int $facultyA;
    private int $facultyB;
    private int $careerA;
    private int $careerB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSecurityAccounts();

        $this->context = $this->createReportingContext();

        // Facultades.
        $this->facultyA = DB::table('facultad')->insertGetId(
            ['nombre' => 'FCyT', 'codigo' => 'FCYT', 'estado' => 'ACTIVO'],
            'id_facultad'
        );
        $this->facultyB = DB::table('facultad')->insertGetId(
            ['nombre' => 'FCE', 'codigo' => 'FCE', 'estado' => 'ACTIVO'],
            'id_facultad'
        );

        // Carreras.
        $this->careerA = DB::table('carrera')->insertGetId(
            ['nombre' => 'SIS', 'codigo' => 'SIS', 'estado' => 'ACTIVO', 'id_facultad' => $this->facultyA],
            'id_carrera'
        );
        $this->careerB = DB::table('carrera')->insertGetId(
            ['nombre' => 'ECO', 'codigo' => 'ECO', 'estado' => 'ACTIVO', 'id_facultad' => $this->facultyB],
            'id_carrera'
        );

        // Materia compartida por las dos carreras, con su par (materia_carrera).
        $subjectId = DB::table('materia')->insertGetId([
            'nombre' => 'Materia reporte',
            'codigo' => 'REP-001',
            'estado' => 'ACTIVO',
        ], 'id_materia');

        foreach ([$this->careerA, $this->careerB] as $careerId) {
            DB::table('materia_carrera')->insert([
                'id_carrera' => $careerId,
                'id_materia' => $subjectId,
                'nivel_semestre' => '1',
                'obligatoria' => true,
                'estado' => 'ACTIVO',
            ]);
        }

        // El examen del contexto apunta al par válido (id_carrera + id_materia juntos).
        DB::table('examen')->where('id_examen', $this->context['id_examen'])->update([
            'id_carrera' => $this->careerA,
            'id_materia' => $subjectId,
        ]);
    }

    private function url(array $overrides = []): string
    {
        return '/api/reportes/intentos-ajenos?' . http_build_query(array_merge([
            'desde' => '2026-10-01',
            'hasta' => '2026-10-31',
        ], $overrides));
    }

    public function test_sin_token_responde_401(): void
    {
        $this->actAsGuest();

        $this->getJson($this->url())->assertUnauthorized();
    }

    public function test_un_docente_recibe_403(): void
    {
        $this->actAs($this->createAccount(Role::DOCENTE));

        $this->getJson($this->url())->assertForbidden();
    }

    public function test_un_auxiliar_recibe_403(): void
    {
        $this->actAs($this->createAccount(Role::AUXILIAR));

        $this->getJson($this->url())->assertForbidden();
    }

    public function test_un_administrador_recibe_el_reporte_vacio(): void
    {
        $this->actAs($this->createAccount(Role::ADMINISTRADOR));

        $this->getJson($this->url())->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total_intentos', 0)
            ->assertJsonPath('meta.total_estudiantes', 0)
            ->assertJsonPath('meta.total_en_central_riesgo', 0);
    }

    public function test_cuenta_solo_los_intentos_por_no_pertenecer(): void
    {
        $this->insertAttempt('NO_PERTENECE');
        $this->insertAttempt('DOCUMENTO_NO_VALIDO');
        $this->insertAttempt('DUPLICADO');
        $this->insertAttempt('AULA_INCORRECTA');
        $this->actAs($this->createAccount(Role::ADMINISTRADOR));

        $this->getJson($this->url())->assertOk()
            ->assertJsonPath('meta.total_intentos', 1)
            ->assertJsonPath('meta.total_estudiantes', 1);
    }

    public function test_agrupa_por_estudiante_y_ordena_por_intentos_descendente(): void
    {
        $reincidente = $this->insertStudent('Sergio', 'Vargas', 'Ibáñez', '202178004');
        $otro = $this->insertStudent('Lucía', 'Salazar', 'Terán', '202216630');

        // 3 intentos para reincidente, 2 para otro.
        // lo que se mide es el agrupamiento por estudiante y el orden por intentos.
        $this->insertAttempt('NO_PERTENECE', $reincidente);
        $this->insertAttempt('NO_PERTENECE', $reincidente);
        $this->insertAttempt('NO_PERTENECE', $reincidente);
        $this->insertAttempt('NO_PERTENECE', $otro);
        $this->insertAttempt('NO_PERTENECE', $otro);

        $this->actAs($this->createAccount(Role::ADMINISTRADOR));

        $response = $this->getJson($this->url())->assertOk();

        $response->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.cod_sis', '202178004')
            ->assertJsonPath('data.0.intentos', 3)
            ->assertJsonPath('data.1.cod_sis', '202216630')
            ->assertJsonPath('data.1.intentos', 2);

        $this->assertCount(1, $response->json('data.0.examenes'));
    }

    public function test_marca_a_los_estudiantes_en_la_central_de_riesgo(): void
    {
        $enCr = $this->insertStudent('En', 'Riesgo', null, '202300001');
        $sinCr = $this->insertStudent('Sin', 'Riesgo', null, '202300002');

        $this->insertAttempt('NO_PERTENECE', $enCr);
        $this->insertAttempt('NO_PERTENECE', $sinCr);
        $this->insertApprovedRecord($this->context, ['id_estudiante' => $enCr]);

        $this->actAs($this->createAccount(Role::ADMINISTRADOR));

        $response = $this->getJson($this->url())->assertOk();

        $this->assertSame(
            ['202300001', '202300002'],
            array_column($response->json('data'), 'cod_sis')
        );
        $this->assertNotNull($response->json('data.0.id_cr_estudiante'));
        $this->assertNull($response->json('data.1.id_cr_estudiante'));
        $response->assertJsonPath('meta.total_en_central_riesgo', 1);
    }

    public function test_filtra_por_rango_de_fechas(): void
    {
        $estudiante = $this->insertStudent('Rango', 'Fecha', null, '202300010');

        $this->insertAttempt('NO_PERTENECE', $estudiante, '2026-10-05 10:00:00+00');
        $this->insertAttempt('NO_PERTENECE', $estudiante, '2026-10-20 10:00:00+00');
        $this->insertAttempt('NO_PERTENECE', $estudiante, '2026-11-05 10:00:00+00');

        $this->actAs($this->createAccount(Role::ADMINISTRADOR));

        $response = $this->getJson($this->url(['desde' => '2026-10-01', 'hasta' => '2026-10-31']))->assertOk();

        $response->assertJsonPath('meta.total_intentos', 2);
    }

    public function test_filtra_por_facultad_del_examen(): void
    {
        $estudiante = $this->insertStudent('Facultad', 'Filtro', null, '202300020');

        // Examen 1: careerA (el del contexto).
        $this->insertAttempt('NO_PERTENECE', $estudiante);

        // Examen 2: careerB (nuevo, con el par materia-carrera válido).
        $examB = DB::table('examen')->insertGetId([
            'nombre_examen' => 'Examen FCE',
            'fecha' => '2026-10-15',
            'hora_inicio' => '10:00',
            'id_tipo_examen' => DB::table('examen')->where('id_examen', $this->context['id_examen'])->value('id_tipo_examen'),
            'id_carrera' => $this->careerB,
            'id_materia' => DB::table('materia_carrera')->where('id_carrera', $this->careerB)->value('id_materia'),
            'id_usuario_docente' => $this->context['id_usuario'],
        ], 'id_examen');
        DB::table('examen_ambiente')->insert([
            'id_examen' => $examB,
            'id_ambiente' => $this->context['id_ambiente'],
        ]);
        $this->insertEntryAttempt($this->context, [
            'id_examen' => $examB,
            'id_estudiante' => $estudiante,
            'motivo' => 'NO_PERTENECE',
            'registrado_en' => '2026-10-15 10:00:00+00',
        ]);

        $this->actAs($this->createAccount(Role::ADMINISTRADOR));

        $this->getJson($this->url(['facultad' => $this->facultyA]))
            ->assertOk()
            ->assertJsonPath('meta.total_intentos', 1);

        $this->getJson($this->url(['facultad' => $this->facultyB]))
            ->assertOk()
            ->assertJsonPath('meta.total_intentos', 1);

        $this->getJson($this->url())
            ->assertOk()
            ->assertJsonPath('meta.total_intentos', 2);
    }

    public function test_falla_si_desde_es_posterior_a_hasta(): void
    {
        $this->actAs($this->createAccount(Role::ADMINISTRADOR));

        $this->getJson($this->url(['desde' => '2026-10-31', 'hasta' => '2026-10-01']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('hasta')
            ->assertJsonPath('errors.hasta.0', 'La fecha de inicio debe ser anterior a la fecha de fin.');
    }

    public function test_falla_si_falta_desde_o_hasta(): void
    {
        $this->actAs($this->createAccount(Role::ADMINISTRADOR));

        $this->getJson('/api/reportes/intentos-ajenos')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['desde', 'hasta']);
    }

    public function test_pdf_responde_con_el_archivo(): void
    {
        $estudiante = $this->insertStudent('Pdf', 'Reporte', null, '202300030');
        $this->insertAttempt('NO_PERTENECE', $estudiante);
        $this->actAs($this->createAccount(Role::ADMINISTRADOR));

        $response = $this->get('/api/reportes/intentos-ajenos/pdf?' . http_build_query([
            'desde' => '2026-10-01',
            'hasta' => '2026-10-31',
        ]))->assertOk();

        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_pdf_con_un_docente_responde_403(): void
    {
        $this->actAs($this->createAccount(Role::DOCENTE));

        $this->get('/api/reportes/intentos-ajenos/pdf?' . http_build_query([
            'desde' => '2026-10-01',
            'hasta' => '2026-10-31',
        ]))->assertForbidden();
    }

    private function insertAttempt(
        string $motivo,
        ?int $studentId = null,
        ?string $registeredAt = null,
        ?int $careerId = null
    ): int {
        $examId = $this->context['id_examen'];

        if ($careerId !== null) {
            $subjectId = DB::table('materia_carrera')
                ->where('id_carrera', $careerId)
                ->value('id_materia');

            DB::table('examen')->where('id_examen', $examId)->update([
                'id_carrera' => $careerId,
                'id_materia' => $subjectId,
            ]);
        }

        return $this->insertEntryAttempt($this->context, array_filter([
            'id_estudiante' => $studentId ?? $this->context['id_estudiante'],
            'motivo' => $motivo,
            'registrado_en' => $registeredAt ?? '2026-10-15 10:00:00+00',
        ]));
    }
}
