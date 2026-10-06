<?php

namespace Tests\Feature\Academic;

use App\Models\Career;
use App\Models\Role;
use App\Models\Subject;
use App\Models\SubjectCareer;
use App\Models\User;
use App\Support\RecordStatus;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** HU-006: listado de pares materia-carrera de la pestaña Asignaciones. */
class SubjectCareerAssignmentListTest extends TestCase
{
    use DatabaseTransactions;

    private const URL = '/api/administracion/asignaciones';

    private int $facultyId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->facultyId = DB::table('facultad')->insertGetId([
            'nombre' => 'Facultad de Ciencias y Tecnologia',
            'codigo' => 'FCYT',
            'estado' => RecordStatus::ACTIVE,
        ], 'id_facultad');
    }

    private function accountWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(
            Role::where('nombre_rol', $roleName)->value('id_rol'),
            ['fecha_inicio' => now()]
        );

        return $user;
    }

    private function actAsAdministrator(): void
    {
        $this->actingAs($this->accountWithRole(Role::ADMINISTRADOR));
    }

    private function career(string $name, string $code, string $status = RecordStatus::ACTIVE): Career
    {
        return Career::create([
            'nombre' => $name,
            'codigo' => $code,
            'estado' => $status,
            'id_facultad' => $this->facultyId,
        ]);
    }

    private function pair(Career $career, string $subjectName, string $subjectCode, string $status = RecordStatus::ACTIVE): Subject
    {
        $subject = Subject::create([
            'nombre' => $subjectName,
            'codigo' => $subjectCode,
            'estado' => RecordStatus::ACTIVE,
        ]);

        SubjectCareer::create([
            'id_carrera' => $career->id_carrera,
            'id_materia' => $subject->id_materia,
            'estado' => $status,
        ]);

        return $subject;
    }

    public function test_administrador_lista_carrera_materia_codigo_nombre_y_estado(): void
    {
        $career = $this->career('Ingenieria de Sistemas', 'SIS');
        $subject = $this->pair($career, 'Inteligencia Artificial', '2008001');

        $this->actAsAdministrator();

        $this->getJson(self::URL)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_carrera', $career->id_carrera)
            ->assertJsonPath('data.0.id_materia', $subject->id_materia)
            ->assertJsonPath('data.0.estado', RecordStatus::ACTIVE)
            ->assertJsonPath('data.0.carrera.nombre', 'Ingenieria de Sistemas')
            ->assertJsonPath('data.0.materia.codigo', '2008001')
            ->assertJsonPath('data.0.materia.nombre', 'Inteligencia Artificial');
    }

    public function test_incluye_pares_inactivos_y_los_marca_como_tales(): void
    {
        $career = $this->career('Ingenieria de Sistemas', 'SIS');
        $this->pair($career, 'Algebra', '2008002', RecordStatus::INACTIVE);

        $this->actAsAdministrator();

        $this->getJson(self::URL)
            ->assertOk()
            ->assertJsonPath('data.0.estado', RecordStatus::INACTIVE);
    }

    public function test_ordena_por_carrera_y_luego_por_materia(): void
    {
        $systems = $this->career('Ingenieria de Sistemas', 'SIS');
        $civil = $this->career('Ingenieria Civil', 'CIV');
        $this->pair($systems, 'Redes', '2008010');
        $this->pair($systems, 'Algebra', '2008011');
        $this->pair($civil, 'Topografia', '2008012');

        $this->actAsAdministrator();

        $rows = $this->getJson(self::URL)->assertOk()->json('data');

        $this->assertSame(
            [
                'Ingenieria Civil/Topografia',
                'Ingenieria de Sistemas/Algebra',
                'Ingenieria de Sistemas/Redes',
            ],
            array_map(fn (array $row): string => $row['carrera']['nombre'] . '/' . $row['materia']['nombre'], $rows)
        );
    }

    public function test_filtra_por_carrera(): void
    {
        $systems = $this->career('Ingenieria de Sistemas', 'SIS');
        $civil = $this->career('Ingenieria Civil', 'CIV');
        $this->pair($systems, 'Redes', '2008010');
        $this->pair($civil, 'Topografia', '2008012');

        $this->actAsAdministrator();

        $rows = $this->getJson(self::URL . '?id_carrera=' . $civil->id_carrera)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->json('data');

        $this->assertSame($civil->id_carrera, $rows[0]['id_carrera']);
        $this->assertSame('Topografia', $rows[0]['materia']['nombre']);
    }

    public function test_una_carrera_sin_pares_devuelve_lista_vacia(): void
    {
        $empty = $this->career('Carrera Nueva', 'NEW');
        $this->pair($this->career('Ingenieria de Sistemas', 'SIS'), 'Redes', '2008010');

        $this->actAsAdministrator();

        $this->getJson(self::URL . '?id_carrera=' . $empty->id_carrera)
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_meta_carreras_lista_solo_las_carreras_con_pares_ordenadas_por_nombre(): void
    {
        $systems = $this->career('Ingenieria de Sistemas', 'SIS');
        $civil = $this->career('Ingenieria Civil', 'CIV');
        $this->career('Carrera Sin Pares', 'VAC');
        $this->pair($systems, 'Redes', '2008010');
        $this->pair($systems, 'Algebra', '2008011');
        $this->pair($civil, 'Topografia', '2008012');

        $this->actAsAdministrator();

        $careers = $this->getJson(self::URL)->assertOk()->json('meta.carreras');

        $this->assertSame(
            ['Ingenieria Civil', 'Ingenieria de Sistemas'],
            array_column($careers, 'nombre')
        );
        $this->assertSame([$civil->id_carrera, $systems->id_carrera], array_column($careers, 'id_carrera'));
        $this->assertSame(['id_carrera', 'nombre', 'codigo', 'id_facultad'], array_keys($careers[0]));
    }

    public function test_meta_carreras_incluye_carreras_inactivas_que_tienen_pares(): void
    {
        $archived = $this->career('Carrera Archivada', 'ARC', RecordStatus::INACTIVE);
        $this->pair($archived, 'Historia', '2008020');

        $this->actAsAdministrator();

        $this->assertSame(
            [$archived->id_carrera],
            array_column($this->getJson(self::URL)->assertOk()->json('meta.carreras'), 'id_carrera')
        );
    }

    public function test_meta_carreras_no_depende_del_filtro_de_la_lista(): void
    {
        $systems = $this->career('Ingenieria de Sistemas', 'SIS');
        $civil = $this->career('Ingenieria Civil', 'CIV');
        $this->pair($systems, 'Redes', '2008010');
        $this->pair($civil, 'Topografia', '2008012');

        $this->actAsAdministrator();

        $unfiltered = $this->getJson(self::URL)->assertOk()->json('meta.carreras');
        $filtered = $this->getJson(self::URL . '?id_carrera=' . $civil->id_carrera)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->json('meta.carreras');

        $this->assertCount(2, $unfiltered);
        $this->assertSame($unfiltered, $filtered);
    }

    public function test_meta_carreras_con_un_filtro_sin_pares_sigue_completa(): void
    {
        $systems = $this->career('Ingenieria de Sistemas', 'SIS');
        $empty = $this->career('Carrera Nueva', 'NEW');
        $this->pair($systems, 'Redes', '2008010');

        $this->actAsAdministrator();

        $this->getJson(self::URL . '?id_carrera=' . $empty->id_carrera)
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonCount(1, 'meta.carreras')
            ->assertJsonPath('meta.carreras.0.id_carrera', $systems->id_carrera);
    }

    public function test_meta_carreras_es_una_lista_vacia_sin_pares(): void
    {
        $this->career('Carrera Nueva', 'NEW');

        $this->actAsAdministrator();

        $this->getJson(self::URL)
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.carreras', []);
    }

    public function test_meta_carreras_no_se_entrega_a_docente_ni_auxiliar(): void
    {
        $career = $this->career('Ingenieria de Sistemas', 'SIS');
        $this->pair($career, 'Redes', '2008010');

        foreach ([Role::DOCENTE, Role::AUXILIAR] as $roleName) {
            $this->actingAs($this->accountWithRole($roleName));

            $this->assertNull($this->getJson(self::URL)->assertForbidden()->json('meta'));
        }
    }

    public function test_el_numero_de_consultas_no_crece_con_la_cantidad_de_pares(): void
    {
        $systems = $this->career('Ingenieria de Sistemas', 'SIS');
        $civil = $this->career('Ingenieria Civil', 'CIV');
        $this->pair($systems, 'Redes', '2008010');

        $this->actAsAdministrator();

        $this->assertCount(1, $this->getJson(self::URL)->assertOk()->json('data'));
        $queriesWithOnePair = $this->countQueries(fn () => $this->getJson(self::URL)->assertOk());

        foreach (range(1, 8) as $index) {
            $this->pair($index % 2 === 0 ? $systems : $civil, "Materia {$index}", '20081' . $index . '0');
        }

        $this->assertCount(9, $this->getJson(self::URL)->assertOk()->json('data'));
        $queriesWithManyPairs = $this->countQueries(fn () => $this->getJson(self::URL)->assertOk());

        $this->assertSame($queriesWithOnePair, $queriesWithManyPairs);
    }

    /**
     * @dataProvider invalidCareerIds
     */
    public function test_id_carrera_invalido_responde_422_y_nunca_500(string $careerId): void
    {
        $this->actAsAdministrator();

        $this->getJson(self::URL . '?id_carrera=' . rawurlencode($careerId))
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_carrera');
    }

    public function test_id_carrera_en_el_limite_de_int4_es_valido_y_no_encuentra_nada(): void
    {
        $this->actAsAdministrator();

        $this->getJson(self::URL . '?id_carrera=2147483647')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_sin_token_responde_401(): void
    {
        $this->getJson(self::URL)->assertUnauthorized();
    }

    public function test_docente_y_auxiliar_reciben_403_incluso_con_filtro_invalido(): void
    {
        foreach ([Role::DOCENTE, Role::AUXILIAR] as $roleName) {
            $this->actingAs($this->accountWithRole($roleName));

            $this->getJson(self::URL)->assertForbidden();
            $this->getJson(self::URL . '?id_carrera=abc')->assertForbidden();
        }
    }

    public function test_administrador_inactivo_recibe_403(): void
    {
        $administrator = $this->accountWithRole(Role::ADMINISTRADOR);
        $administrator->forceFill(['estado' => User::ESTADO_INACTIVO])->save();

        $this->actingAs($administrator)->getJson(self::URL)->assertForbidden();
    }

    public function invalidCareerIds(): array
    {
        return [
            'no numerico' => ['abc'],
            'decimal' => ['1.5'],
            'cero' => ['0'],
            'negativo' => ['-3'],
            'primer valor sobre int4' => ['2147483648'],
            'diez nueves' => ['9999999999'],
            'desbordamiento enorme' => ['99999999999999999999'],
        ];
    }

    private function countQueries(callable $request): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $request();

        $total = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $total;
    }
}
