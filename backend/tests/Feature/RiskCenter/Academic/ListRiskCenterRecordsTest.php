<?php

namespace Tests\Feature\RiskCenter\Academic;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsRiskCenterRecords;
use Tests\Concerns\SeedsSecurityAccounts;
use Tests\TestCase;

/**
 * HU-046: GET /api/central-riesgo, el listado institucional y de solo lectura de la Central de Riesgo.
 */
class ListRiskCenterRecordsTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsRiskCenterRecords;
    use SeedsSecurityAccounts;

    private array $context;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedSecurityAccounts();
        $this->context = $this->createReportingContext();
    }

    public function test_sin_token_responde_401(): void
    {
        $this->actAsGuest();

        $this->getJson('/api/central-riesgo')->assertUnauthorized();
    }

    public function test_un_auxiliar_recibe_403(): void
    {
        $this->actAs($this->createAccount(Role::AUXILIAR));

        $this->getJson('/api/central-riesgo')->assertForbidden();
    }

    public function test_una_cuenta_sin_rol_recibe_403(): void
    {
        $this->actAs($this->createAccount());

        $this->getJson('/api/central-riesgo')->assertForbidden();
    }

    public function test_una_cuenta_inactiva_recibe_403_y_su_token_se_revoca(): void
    {
        $user = $this->createAccount(Role::DOCENTE, User::ESTADO_INACTIVO);
        $token = $user->createToken('prueba')->plainTextToken;

        $this->asToken($token)->getJson('/api/central-riesgo')
            ->assertForbidden()
            ->assertJsonPath('motivo', 'cuenta_inactiva');
    }

    /** @return array<string, array{0: string}> */
    public function allowedRoles(): array
    {
        return ['docente' => [Role::DOCENTE], 'administrador' => [Role::ADMINISTRADOR]];
    }

    /**
     * @dataProvider allowedRoles
     */
    public function test_docente_y_administrador_reciben_200(string $role): void
    {
        $this->actAs($this->createAccount($role));

        $this->getJson('/api/central-riesgo')->assertOk()->assertJsonPath('meta.total', 0);
    }

    public function test_lista_los_registros_del_mas_reciente_al_mas_antiguo_con_sus_campos(): void
    {
        $pair = $this->insertCareerSubjectPair('Ingenieria de Sistemas', 'Calculo I');
        DB::table('examen')->where('id_examen', $this->context['id_examen'])->update([
            'nombre_examen' => '1er Parcial',
            'id_carrera' => $pair['id_carrera'],
            'id_materia' => $pair['id_materia'],
        ]);
        $jose = $this->insertStudent('José Luis', 'Pérez', 'Núñez', '202312345');
        $lucia = $this->insertStudent('Lucía', 'Salazar', 'Terán', '202216630');

        $oldest = $this->insertApprovedRecord($this->context, ['id_estudiante' => $jose], '2026-06-12 12:00:00+00');
        $newest = $this->insertApprovedRecord($this->context, ['id_estudiante' => $jose], '2026-10-20 12:00:00+00');
        $middle = $this->insertApprovedRecord($this->context, ['id_estudiante' => $lucia], '2026-10-14 12:00:00+00');
        $this->actAs($this->createAccount(Role::DOCENTE));

        $response = $this->getJson('/api/central-riesgo')->assertOk();

        $this->assertSame(
            [$newest['id_cr_estudiante'], $middle['id_cr_estudiante'], $oldest['id_cr_estudiante']],
            array_column($response->json('data'), 'id_cr_estudiante')
        );
        $response->assertJsonPath('data.0.estudiante.nombre_completo', 'José Luis Pérez Núñez')
            ->assertJsonPath('data.0.estudiante.cod_sis', '202312345')
            ->assertJsonPath('data.0.examen.nombre', '1er Parcial')
            ->assertJsonPath('data.0.examen.materia', 'Calculo I')
            ->assertJsonPath('data.0.tipo_falta.id_falta', $this->context['id_tipo_falta'])
            ->assertJsonPath('data.0.fecha_registro', '2026-10-20T12:00:00+00:00')
            ->assertJsonPath('data.1.estudiante.nombre_completo', 'Lucía Salazar Terán')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonStructure(['data', 'links', 'meta' => ['current_page', 'last_page', 'per_page', 'total', 'from', 'to']]);
    }

    public function test_la_respuesta_no_trae_evidencia_ni_datos_de_quien_reporto(): void
    {
        $this->insertApprovedRecord($this->context, [
            'evidencia_url' => 'http://localhost/storage/evidencias/reportes/1.png',
            'evidencia_public_id' => 'evidencias/reportes/1.png',
        ]);
        $this->actAs($this->createAccount(Role::DOCENTE));

        $row = $this->getJson('/api/central-riesgo')->assertOk()->json('data.0');

        $this->assertArrayNotHasKey('evidencia', $row);
        $this->assertArrayNotHasKey('reportante', $row);
        $this->assertStringNotContainsString('storage/evidencias', json_encode($row));
    }

    public function test_el_registro_es_institucional_un_docente_ve_los_de_examenes_ajenos(): void
    {
        $record = $this->insertApprovedRecord($this->context);
        $stranger = $this->createAccount(Role::DOCENTE);

        $this->assertNotSame($stranger->id_usuario, $this->context['id_usuario']);
        $this->actAs($stranger);

        $this->getJson('/api/central-riesgo')
            ->assertOk()
            ->assertJsonPath('data.0.id_cr_estudiante', $record['id_cr_estudiante']);
    }

    public function test_solo_aparecen_los_reportes_aprobados_que_tienen_fila_en_la_central(): void
    {
        $approved = $this->insertApprovedRecord($this->context);
        $this->insertStudentReport($this->context);
        $this->insertStudentReport($this->context, [
            'estado' => 'RECHAZADO',
            'fecha_revision' => '2026-10-02 09:00:00+00',
            'id_usuario_revisor' => $this->context['id_usuario'],
        ]);
        $this->actAs($this->createAccount(Role::DOCENTE));

        $this->getJson('/api/central-riesgo')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id_cr_estudiante', $approved['id_cr_estudiante']);
    }

    public function test_una_fecha_igual_se_desempata_por_el_id_mas_reciente(): void
    {
        $first = $this->insertApprovedRecord($this->context, [], '2026-10-01 12:00:00+00');
        $second = $this->insertApprovedRecord($this->context, [], '2026-10-01 12:00:00+00');
        $this->actAs($this->createAccount(Role::DOCENTE));

        $this->assertSame(
            [$second['id_cr_estudiante'], $first['id_cr_estudiante']],
            array_column($this->getJson('/api/central-riesgo')->json('data'), 'id_cr_estudiante')
        );
    }

    /** @return array<string, array{0: string}> */
    public function josePerezSearches(): array
    {
        return [
            'sin acento' => ['jose'],
            'con acento' => ['José'],
            'mayusculas' => ['JOSE PEREZ'],
            'apellido y nombre' => ['perez jose'],
            'apellido con tilde' => ['pérez'],
            'codigo SIS' => ['202312345'],
            'parte del codigo SIS' => ['2023123'],
            'con espacios alrededor' => ['  jose  '],
        ];
    }

    /**
     * @dataProvider josePerezSearches
     */
    public function test_busca_a_un_estudiante_sin_acentos_por_nombre_o_codigo_sis(string $search): void
    {
        $jose = $this->insertStudent('José', 'Pérez', 'Mamani', '202312345');
        $other = $this->insertStudent('Lucía', 'Salazar', null, '202216630');
        $joseRecords = [
            $this->insertApprovedRecord($this->context, ['id_estudiante' => $jose], '2026-10-01 12:00:00+00'),
            $this->insertApprovedRecord($this->context, ['id_estudiante' => $jose], '2026-10-02 12:00:00+00'),
        ];
        $this->insertApprovedRecord($this->context, ['id_estudiante' => $other], '2026-10-03 12:00:00+00');
        $this->actAs($this->createAccount(Role::DOCENTE));

        $response = $this->getJson('/api/central-riesgo?' . http_build_query(['q' => $search]))->assertOk();

        $this->assertEqualsCanonicalizing(
            array_column($joseRecords, 'id_cr_estudiante'),
            array_column($response->json('data'), 'id_cr_estudiante')
        );
        $response->assertJsonPath('meta.total', 2);
    }

    public function test_una_busqueda_sin_coincidencias_devuelve_una_lista_vacia(): void
    {
        $this->insertApprovedRecord($this->context);
        $this->actAs($this->createAccount(Role::DOCENTE));

        $this->getJson('/api/central-riesgo?q=zzz')
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.total', 0);
    }

    public function test_los_comodines_de_like_se_buscan_como_texto(): void
    {
        $student = $this->insertStudent('Ana', 'Perez', null, '202300001');
        $this->insertApprovedRecord($this->context, ['id_estudiante' => $student]);
        $this->actAs($this->createAccount(Role::DOCENTE));

        foreach (['%', '_', 'a%a', 'p_rez'] as $wildcard) {
            $this->getJson('/api/central-riesgo?' . http_build_query(['q' => $wildcard]))
                ->assertOk()
                ->assertJsonPath('meta.total', 0);
        }
    }

    public function test_filtra_por_tipo_de_falta(): void
    {
        $copy = $this->insertFaultType('Copia');
        $impersonation = $this->insertFaultType('Suplantacion');
        $copyRecord = $this->insertApprovedRecord($this->context, ['id_tipo_falta' => $copy]);
        $this->insertApprovedRecord($this->context, ['id_tipo_falta' => $impersonation]);
        $this->actAs($this->createAccount(Role::DOCENTE));

        $this->getJson('/api/central-riesgo?tipo=' . $copy)
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id_cr_estudiante', $copyRecord['id_cr_estudiante'])
            ->assertJsonPath('data.0.tipo_falta.nombre', 'Copia');
    }

    public function test_combina_busqueda_y_tipo(): void
    {
        $copy = $this->insertFaultType('Copia');
        $jose = $this->insertStudent('José', 'Pérez', null, '202312345');
        $match = $this->insertApprovedRecord($this->context, ['id_estudiante' => $jose, 'id_tipo_falta' => $copy]);
        $this->insertApprovedRecord($this->context, ['id_estudiante' => $jose]);
        $this->insertApprovedRecord($this->context, ['id_tipo_falta' => $copy]);
        $this->actAs($this->createAccount(Role::DOCENTE));

        $response = $this->getJson('/api/central-riesgo?' . http_build_query(['q' => 'jose', 'tipo' => $copy]))
            ->assertOk();

        $this->assertSame([$match['id_cr_estudiante']], array_column($response->json('data'), 'id_cr_estudiante'));
    }

    public function test_pagina_con_un_limite_por_pagina(): void
    {
        foreach (range(1, 5) as $day) {
            $this->insertApprovedRecord($this->context, [], "2026-10-0{$day} 12:00:00+00");
        }
        $this->actAs($this->createAccount(Role::DOCENTE));

        $second = $this->getJson('/api/central-riesgo?per_page=2&page=2')->assertOk();

        $second->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.from', 3)
            ->assertJsonPath('meta.to', 4);
        $this->assertSame(['2026-10-03', '2026-10-02'], array_map(
            fn (string $date) => substr($date, 0, 10),
            array_column($second->json('data'), 'fecha_registro')
        ));
    }

    public function test_el_limite_por_defecto_es_diez_y_una_pagina_fuera_de_rango_viene_vacia(): void
    {
        foreach (range(1, 12) as $unused) {
            $this->insertApprovedRecord($this->context);
        }
        $this->actAs($this->createAccount(Role::DOCENTE));

        $this->getJson('/api/central-riesgo')->assertOk()->assertJsonCount(10, 'data')->assertJsonPath('meta.last_page', 2);
        $this->getJson('/api/central-riesgo?page=9')->assertOk()->assertJsonPath('data', []);
    }

    /** @return array<string, array{0: array<string, mixed>, 1: string}> */
    public function invalidParameters(): array
    {
        return [
            'pagina cero' => [['page' => 0], 'page'],
            'pagina negativa' => [['page' => -1], 'page'],
            'pagina enorme' => [['page' => 100001], 'page'],
            'pagina que desborda un entero' => [['page' => '99999999999999999999'], 'page'],
            'pagina no numerica' => [['page' => 'abc'], 'page'],
            'por pagina cero' => [['per_page' => 0], 'per_page'],
            'por pagina sobre el tope' => [['per_page' => 51], 'per_page'],
            'tipo cero' => [['tipo' => 0], 'tipo'],
            'tipo mayor al entero de la base' => [['tipo' => 2147483648], 'tipo'],
            'tipo no numerico' => [['tipo' => 'copia'], 'tipo'],
            'busqueda demasiado larga' => [['q' => str_repeat('a', 51)], 'q'],
        ];
    }

    /**
     * @dataProvider invalidParameters
     */
    public function test_los_parametros_fuera_de_rango_responden_422(array $query, string $field): void
    {
        $this->actAs($this->createAccount(Role::DOCENTE));

        $this->getJson('/api/central-riesgo?' . http_build_query($query))
            ->assertStatus(422)
            ->assertJsonValidationErrors($field);
    }

    public function test_el_numero_de_consultas_no_crece_con_la_cantidad_de_filas(): void
    {
        $pair = $this->insertCareerSubjectPair('Sistemas', 'Calculo');
        DB::table('examen')->where('id_examen', $this->context['id_examen'])->update([
            'id_carrera' => $pair['id_carrera'],
            'id_materia' => $pair['id_materia'],
        ]);
        $this->insertApprovedRecord($this->context);
        $this->insertApprovedRecord($this->context);
        $this->actAs($this->createAccount(Role::DOCENTE));

        $few = $this->countQueriesOf('/api/central-riesgo');

        foreach (range(1, 8) as $unused) {
            $other = $this->insertStudent('Otro', 'Estudiante', null, (string) random_int(100000000, 999999999));
            $this->insertApprovedRecord($this->context, [
                'id_estudiante' => $other,
                'id_tipo_falta' => $this->insertFaultType('Falta ' . uniqid()),
            ]);
        }

        $many = $this->countQueriesOf('/api/central-riesgo');

        $this->assertSame($few, $many, 'El listado hace consultas por fila (N+1).');
        $this->assertLessThanOrEqual(12, $many);
    }

    public function test_la_busqueda_y_el_filtro_tampoco_hacen_consultas_por_fila(): void
    {
        $copy = $this->insertFaultType('Copia');
        $this->actAs($this->createAccount(Role::DOCENTE));
        $url = '/api/central-riesgo?' . http_build_query(['q' => 'nombre', 'tipo' => $copy]);

        $this->insertApprovedRecord($this->context, ['id_tipo_falta' => $copy]);
        $few = $this->countQueriesOf($url);

        foreach (range(1, 6) as $unused) {
            $this->insertApprovedRecord($this->context, ['id_tipo_falta' => $copy]);
        }

        $this->assertSame($few, $this->countQueriesOf($url));
    }

    private function asToken(string $token): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    private function countQueriesOf(string $url): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->getJson($url)->assertOk();

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
