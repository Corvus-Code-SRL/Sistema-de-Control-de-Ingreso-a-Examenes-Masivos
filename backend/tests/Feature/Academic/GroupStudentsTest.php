<?php

namespace Tests\Feature\Academic;

use App\Models\Role;
use App\Support\RecordStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsAcademicCatalog;
use Tests\Concerns\SeedsSecurityAccounts;
use Tests\TestCase;

/**
 * HU-020: GET /api/grupos/{id_grupo}/estudiantes — los estudiantes inscritos en un grupo propio,
 * 50 por página, ordenados por apellido y con búsqueda sin tildes por nombre o código SIS.
 */
class GroupStudentsTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsAcademicCatalog;
    use SeedsSecurityAccounts;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAcademicCatalog();
    }

    public function test_lista_los_inscritos_con_el_total_y_ordenados_por_apellido(): void
    {
        // CA-1: 35 estudiantes; los nombrados prueban el orden, el resto completa el total.
        $this->enrollStudents($this->grupoPropioId, 31);
        $this->enroll($this->grupoPropioId, '202100001', 'Óscar', 'Ávila', 'Zurita');
        $this->enroll($this->grupoPropioId, '202100002', 'Bruno', 'Ávila', null);
        $this->enroll($this->grupoPropioId, '202100003', 'Ana', 'Ávila', 'Zurita');
        $this->enroll($this->grupoPropioId, '202100004', 'Carla', 'Arnez', 'Mendoza');

        $response = $this->getJson($this->url($this->grupoPropioId))
            ->assertOk()
            ->assertJsonPath('meta.total', 35)
            ->assertJsonPath('meta.total_inscritos', 35)
            ->assertJsonPath('meta.per_page', 50)
            ->assertJsonCount(35, 'data')
            ->assertJsonStructure([
                'data' => [['id_estudiante', 'cod_sis', 'apellido_paterno', 'apellido_materno', 'nombre']],
                'meta' => ['current_page', 'last_page', 'per_page', 'total', 'total_inscritos'],
            ]);

        $this->assertSame(
            ['202100004', '202100002', '202100003', '202100001'],
            array_slice(array_column($response->json('data'), 'cod_sis'), 0, 4)
        );
    }

    public function test_no_expone_ci_ni_datos_de_contacto(): void
    {
        $this->enrollStudents($this->grupoPropioId, 1);

        $student = $this->getJson($this->url($this->grupoPropioId))->assertOk()->json('data.0');

        $this->assertSame(
            ['id_estudiante', 'cod_sis', 'apellido_paterno', 'apellido_materno', 'nombre'],
            array_keys($student)
        );
    }

    /** @dataProvider nunezSearches */
    public function test_busca_por_nombre_sin_tildes_ni_mayusculas_o_por_codigo_sis(string $search): void
    {
        // CA-2
        $this->enroll($this->grupoPropioId, '202155501', 'María', 'Núñez', 'Quiroga');
        $this->enroll($this->grupoPropioId, '202100002', 'Mario', 'Rojas', null);
        $this->enrollStudents($this->grupoPropioId, 3);

        $this->getJson($this->url($this->grupoPropioId, ['q' => $search]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.cod_sis', '202155501')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.total_inscritos', 5);
    }

    /** @return array<string, array{0: string}> */
    public function nunezSearches(): array
    {
        return [
            'apellido sin tilde' => ['nunez'],
            'mayúsculas con tilde' => ['NÚÑEZ'],
            'nombre y apellido' => ['maria nunez'],
            'palabras en otro orden' => ['  Núñez   MARÍA '],
            'código SIS completo' => ['202155501'],
            'código SIS parcial' => ['5550'],
        ];
    }

    public function test_un_porcentaje_o_guion_bajo_se_buscan_como_texto(): void
    {
        $this->enrollStudents($this->grupoPropioId, 3);

        foreach (['%', '_', 'a%b', '\\'] as $search) {
            $this->getJson($this->url($this->grupoPropioId, ['q' => $search]))
                ->assertOk()
                ->assertJsonCount(0, 'data')
                ->assertJsonPath('meta.total', 0)
                ->assertJsonPath('meta.total_inscritos', 3);
        }
    }

    public function test_pagina_de_50_en_50(): void
    {
        // CA-3
        $this->enrollStudents($this->grupoPropioId, 120);

        $this->getJson($this->url($this->grupoPropioId))
            ->assertOk()
            ->assertJsonCount(50, 'data')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonPath('meta.total', 120);

        $second = $this->getJson($this->url($this->grupoPropioId, ['page' => 2]))
            ->assertOk()
            ->assertJsonCount(50, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->json('data');

        $third = $this->getJson($this->url($this->grupoPropioId, ['page' => 3]))
            ->assertOk()
            ->assertJsonCount(20, 'data')
            ->json('data');

        $this->assertSame([], array_intersect(
            array_column($second, 'id_estudiante'),
            array_column($third, 'id_estudiante')
        ));
    }

    public function test_el_cliente_no_elige_el_tamano_de_pagina(): void
    {
        $this->enrollStudents($this->grupoPropioId, 60);

        $this->getJson($this->url($this->grupoPropioId, ['per_page' => 500]))
            ->assertOk()
            ->assertJsonCount(50, 'data')
            ->assertJsonPath('meta.per_page', 50);
    }

    public function test_un_grupo_sin_inscritos_responde_vacio(): void
    {
        // CA-4
        $this->getJson($this->url($this->grupoPropioId))
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0)
            ->assertJsonPath('meta.total_inscritos', 0);
    }

    public function test_no_lista_inscripciones_inactivas_ni_estudiantes_de_otros_grupos(): void
    {
        $this->enroll($this->grupoPropioId, '202100001', 'Activa', 'Prueba', null);
        $this->enroll($this->grupoPropioId, '202100002', 'Retirada', 'Prueba', null, RecordStatus::INACTIVE);
        $this->enrollStudents($this->grupoAjenoId, 4);

        $this->getJson($this->url($this->grupoPropioId))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nombre', 'Activa')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.total_inscritos', 1);
    }

    public function test_un_grupo_propio_de_un_periodo_anterior_se_puede_consultar(): void
    {
        $this->enrollStudents($this->grupoPeriodoAnteriorId, 2);

        $this->getJson($this->url($this->grupoPeriodoAnteriorId))
            ->assertOk()
            ->assertJsonPath('meta.total_inscritos', 2);
    }

    public function test_el_docente_de_otro_grupo_recibe_403_y_no_ve_sus_estudiantes(): void
    {
        $this->enroll($this->grupoAjenoId, '202100001', 'Reservada', 'Prueba', null);

        $response = $this->getJson($this->url($this->grupoAjenoId))
            ->assertForbidden()
            ->assertJsonPath('message', 'Solo el docente que dicta el grupo puede ver su detalle.')
            ->assertDontSee('Reservada');

        $this->assertArrayNotHasKey('data', $response->json());
    }

    public function test_un_auxiliar_recibe_403(): void
    {
        $this->actAs($this->createAccount(Role::AUXILIAR));

        $this->getJson($this->url($this->grupoPropioId))->assertForbidden();
    }

    public function test_un_administrador_recibe_403(): void
    {
        $this->actAs($this->createAccount(Role::ADMINISTRADOR));

        $this->getJson($this->url($this->grupoPropioId))->assertForbidden();
    }

    public function test_sin_token_responde_401(): void
    {
        $this->actAsGuest();

        $this->getJson($this->url($this->grupoPropioId))->assertUnauthorized();
    }

    public function test_responde_404_cuando_el_grupo_no_existe(): void
    {
        $this->getJson($this->url(999999999))
            ->assertNotFound()
            ->assertJsonPath('message', 'No existe el grupo indicado.');
    }

    /** @dataProvider outOfPatternIdentifiers */
    public function test_un_id_fuera_del_patron_responde_404_y_no_500(string $id): void
    {
        $this->getJson("/api/grupos/{$id}/estudiantes")->assertNotFound();
    }

    /** @return array<string, array{0: string}> */
    public function outOfPatternIdentifiers(): array
    {
        return [
            'no numérico' => ['abc'],
            'negativo' => ['-5'],
            'diez dígitos' => ['1234567890'],
            'sobre el rango de int4' => ['2147483648'],
        ];
    }

    public function test_rechaza_con_422_el_id_cero(): void
    {
        $this->getJson($this->url(0))
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_grupo');
    }

    /** @dataProvider invalidQueries */
    public function test_rechaza_con_422_una_consulta_invalida(array $query, string $field): void
    {
        $this->getJson($this->url($this->grupoPropioId, $query))
            ->assertStatus(422)
            ->assertJsonValidationErrors($field);
    }

    /** @return array<string, array{0: array<string, mixed>, 1: string}> */
    public function invalidQueries(): array
    {
        return [
            'búsqueda de 51 caracteres' => [['q' => str_repeat('a', 51)], 'q'],
            'página cero' => [['page' => 0], 'page'],
            'página desbordada' => [['page' => 100001], 'page'],
            'página no numérica' => [['page' => 'dos'], 'page'],
        ];
    }

    private function enroll(
        int $groupId,
        string $sisCode,
        string $name,
        string $paternalSurname,
        ?string $maternalSurname,
        string $status = RecordStatus::ACTIVE
    ): void {
        $studentId = DB::table('estudiante')->insertGetId([
            'cod_sis' => $sisCode,
            'nombre' => $name,
            'apellido_paterno' => $paternalSurname,
            'apellido_materno' => $maternalSurname,
            'estado' => RecordStatus::ACTIVE,
        ], 'id_estudiante');

        DB::table('grupo_estudiante')->insert([
            'id_grupo' => $groupId,
            'id_estudiante' => $studentId,
            'fecha_inscripcion' => '2026-02-15',
            'estado' => $status,
        ]);
    }

    /** @param array<string, mixed> $query */
    private function url(int $groupId, array $query = []): string
    {
        $url = "/api/grupos/{$groupId}/estudiantes";

        return $query === [] ? $url : $url . '?' . http_build_query($query);
    }
}
