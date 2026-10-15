<?php

namespace Tests\Feature\Academic;

use App\Models\AcademicOffering;
use App\Models\AcademicYear;
use App\Models\Career;
use App\Models\Period;
use App\Models\PeriodType;
use App\Models\Role;
use App\Models\Subject;
use App\Models\SubjectCareer;
use App\Models\User;
use App\Support\RecordStatus;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** HU-006: listado de ofertas (par materia-carrera por período) de la vista Asignaciones. */
class SubjectCareerAssignmentListTest extends TestCase
{
    use DatabaseTransactions;

    private const URL = '/api/administracion/asignaciones';

    private int $facultyId;
    private int $periodId;
    private int $otherPeriodId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->facultyId = DB::table('facultad')->insertGetId([
            'nombre' => 'Facultad de Ciencias y Tecnologia',
            'codigo' => 'FCYT',
            'estado' => RecordStatus::ACTIVE,
        ], 'id_facultad');

        $this->periodId = $this->createPeriod(2026, 1, true);
        $this->otherPeriodId = $this->createPeriod(2026, 2);
    }

    private function createPeriod(int $year, int $code, bool $current = false): int
    {
        $yearId = AcademicYear::query()->firstOrCreate(
            ['anio' => $year],
            ['estado' => RecordStatus::ACTIVE]
        )->id_gestion;

        if ($current) {
            Period::query()->where('vigente', true)->update(['vigente' => false]);
        }

        return Period::create([
            'id_gestion' => $yearId,
            'id_periodo' => PeriodType::query()->where('codigo', $code)->value('id_periodo'),
            'vigente' => $current,
            'estado' => RecordStatus::ACTIVE,
        ])->id_gestion_periodo;
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

    private function subject(string $name, string $code): Subject
    {
        return Subject::create([
            'nombre' => $name,
            'codigo' => $code,
            'estado' => RecordStatus::ACTIVE,
        ]);
    }

    /** Crea la materia, su par con la carrera y la oferta en el período (por omisión, el primero). */
    private function offer(
        Career $career,
        string $subjectName,
        string $subjectCode,
        string $status = RecordStatus::ACTIVE,
        ?int $periodId = null
    ): Subject {
        $subject = $this->subject($subjectName, $subjectCode);

        SubjectCareer::create([
            'id_carrera' => $career->id_carrera,
            'id_materia' => $subject->id_materia,
            'estado' => RecordStatus::ACTIVE,
        ]);

        $this->offerExisting($career, $subject, $status, $periodId);

        return $subject;
    }

    private function offerExisting(
        Career $career,
        Subject $subject,
        string $status = RecordStatus::ACTIVE,
        ?int $periodId = null
    ): void {
        AcademicOffering::create([
            'id_carrera' => $career->id_carrera,
            'id_materia' => $subject->id_materia,
            'id_gestion_periodo' => $periodId ?? $this->periodId,
            'estado' => $status,
        ]);
    }

    private function url(array $query = [], ?int $periodId = null): string
    {
        return self::URL . '?' . http_build_query(['id_periodo' => $periodId ?? $this->periodId] + $query);
    }

    public function test_administrador_lista_carrera_materia_codigo_nombre_estado_y_periodo(): void
    {
        $career = $this->career('Ingenieria de Sistemas', 'SIS');
        $subject = $this->offer($career, 'Inteligencia Artificial', '2008001');

        $this->actAsAdministrator();

        $this->getJson($this->url())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_carrera', $career->id_carrera)
            ->assertJsonPath('data.0.id_materia', $subject->id_materia)
            ->assertJsonPath('data.0.id_periodo', $this->periodId)
            ->assertJsonPath('data.0.estado', RecordStatus::ACTIVE)
            ->assertJsonPath('data.0.carrera.nombre', 'Ingenieria de Sistemas')
            ->assertJsonPath('data.0.materia.codigo', '2008001')
            ->assertJsonPath('data.0.materia.nombre', 'Inteligencia Artificial')
            ->assertJsonPath('data.0.periodo.etiqueta', '1/2026')
            ->assertJsonPath('meta.periodo.id_periodo', $this->periodId)
            ->assertJsonPath('meta.periodo.etiqueta', '1/2026')
            ->assertJsonPath('meta.periodo.vigente', true);
    }

    public function test_lista_solo_lo_ofrecido_en_el_periodo_pedido(): void
    {
        $career = $this->career('Ingenieria de Sistemas', 'SIS');
        $this->offer($career, 'Redes', '2008010');
        $this->offer($career, 'Algebra', '2008011', RecordStatus::ACTIVE, $this->otherPeriodId);

        $this->actAsAdministrator();

        $current = $this->getJson($this->url())->assertOk();

        $this->assertSame(
            ['Redes'],
            array_column(array_column($current->json('data'), 'materia'), 'nombre')
        );

        $other = $this->getJson($this->url([], $this->otherPeriodId))->assertOk();

        $this->assertSame(
            ['Algebra'],
            array_column(array_column($other->json('data'), 'materia'), 'nombre')
        );
        $other->assertJsonPath('meta.periodo.etiqueta', '2/2026')
            ->assertJsonPath('meta.periodo.vigente', false);
    }

    public function test_una_materia_ofrecida_en_dos_periodos_aparece_en_cada_uno(): void
    {
        $career = $this->career('Ingenieria de Sistemas', 'SIS');
        $subject = $this->offer($career, 'Electiva', '2008030');
        $this->offerExisting($career, $subject, RecordStatus::ACTIVE, $this->otherPeriodId);

        $this->actAsAdministrator();

        $this->getJson($this->url())->assertOk()->assertJsonCount(1, 'data');
        $this->getJson($this->url([], $this->otherPeriodId))->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_incluye_ofertas_inactivas_y_las_marca_como_tales(): void
    {
        $career = $this->career('Ingenieria de Sistemas', 'SIS');
        $this->offer($career, 'Algebra', '2008002', RecordStatus::INACTIVE);

        $this->actAsAdministrator();

        $this->getJson($this->url())
            ->assertOk()
            ->assertJsonPath('data.0.estado', RecordStatus::INACTIVE);
    }

    public function test_un_par_sin_oferta_en_el_periodo_no_se_lista(): void
    {
        $career = $this->career('Ingenieria de Sistemas', 'SIS');
        $subject = $this->subject('Sin oferta', '2008040');
        SubjectCareer::create([
            'id_carrera' => $career->id_carrera,
            'id_materia' => $subject->id_materia,
            'estado' => RecordStatus::ACTIVE,
        ]);

        $this->actAsAdministrator();

        $this->getJson($this->url())->assertOk()->assertJsonPath('data', []);
    }

    /** `fecha_registro` toma `now()`, igual para todo el test: se fija para controlar la recencia. */
    private function registeredAt(Career $career, Subject $subject, string $at, ?int $periodId = null): void
    {
        DB::table('oferta_academica')
            ->where('id_carrera', $career->id_carrera)
            ->where('id_materia', $subject->id_materia)
            ->where('id_gestion_periodo', $periodId ?? $this->periodId)
            ->update(['fecha_registro' => $at]);
    }

    /** @return string[] «Carrera/Materia» de cada fila, en el orden recibido. */
    private function labels(array $rows): array
    {
        return array_map(
            fn (array $row): string => $row['carrera']['nombre'] . '/' . $row['materia']['nombre'],
            $rows
        );
    }

    public function test_ordena_de_la_mas_reciente_a_la_mas_antigua_entre_carreras(): void
    {
        $systems = $this->career('Ingenieria de Sistemas', 'SIS');
        $civil = $this->career('Ingenieria Civil', 'CIV');
        $networks = $this->offer($systems, 'Redes', '2008010');
        $algebra = $this->offer($systems, 'Algebra', '2008011');
        $surveying = $this->offer($civil, 'Topografia', '2008012');
        $this->registeredAt($systems, $algebra, '2026-03-01 10:00:00-04');
        $this->registeredAt($civil, $surveying, '2026-03-01 11:00:00-04');
        $this->registeredAt($systems, $networks, '2026-03-01 12:00:00-04');

        $this->actAsAdministrator();

        $rows = $this->getJson($this->url())->assertOk()->json('data');

        $this->assertSame(
            [
                'Ingenieria de Sistemas/Redes',
                'Ingenieria Civil/Topografia',
                'Ingenieria de Sistemas/Algebra',
            ],
            $this->labels($rows)
        );
    }

    public function test_el_empate_de_fecha_se_resuelve_por_carrera_y_codigo_de_materia_de_forma_estable(): void
    {
        $systems = $this->career('Ingenieria de Sistemas', 'SIS');
        $civil = $this->career('Ingenieria Civil', 'CIV');
        $networks = $this->offer($systems, 'Redes', '2008010');
        $algebra = $this->offer($systems, 'Algebra', '2008011');
        $surveying = $this->offer($civil, 'Topografia', '2008012');
        $sameInstant = '2026-03-01 10:00:00-04';
        $this->registeredAt($systems, $networks, $sameInstant);
        $this->registeredAt($systems, $algebra, $sameInstant);
        $this->registeredAt($civil, $surveying, $sameInstant);

        $this->actAsAdministrator();

        $first = $this->labels($this->getJson($this->url())->assertOk()->json('data'));
        $second = $this->labels($this->getJson($this->url())->assertOk()->json('data'));

        // Redes (2008010) va antes que Algebra (2008011): desempata el código, no el nombre.
        $this->assertSame(
            [
                'Ingenieria Civil/Topografia',
                'Ingenieria de Sistemas/Redes',
                'Ingenieria de Sistemas/Algebra',
            ],
            $first
        );
        $this->assertSame($first, $second);
    }

    public function test_conserva_el_orden_por_recientes_con_el_filtro_de_carrera(): void
    {
        $systems = $this->career('Ingenieria de Sistemas', 'SIS');
        $civil = $this->career('Ingenieria Civil', 'CIV');
        $networks = $this->offer($systems, 'Redes', '2008010');
        $algebra = $this->offer($systems, 'Algebra', '2008011');
        $surveying = $this->offer($civil, 'Topografia', '2008012');
        $this->registeredAt($systems, $algebra, '2026-03-01 10:00:00-04');
        $this->registeredAt($civil, $surveying, '2026-03-01 11:00:00-04');
        $this->registeredAt($systems, $networks, '2026-03-01 12:00:00-04');

        $this->actAsAdministrator();

        $rows = $this->getJson($this->url(['id_carrera' => $systems->id_carrera]))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->json('data');

        $this->assertSame(
            ['Ingenieria de Sistemas/Redes', 'Ingenieria de Sistemas/Algebra'],
            $this->labels($rows)
        );
    }

    public function test_el_orden_por_recientes_se_aplica_dentro_de_cada_periodo(): void
    {
        $systems = $this->career('Ingenieria de Sistemas', 'SIS');
        $civil = $this->career('Ingenieria Civil', 'CIV');
        $networks = $this->offer($systems, 'Redes', '2008010');
        $surveying = $this->offer($civil, 'Topografia', '2008012');
        $this->offerExisting($systems, $networks, RecordStatus::ACTIVE, $this->otherPeriodId);
        $this->offerExisting($civil, $surveying, RecordStatus::ACTIVE, $this->otherPeriodId);

        $this->registeredAt($systems, $networks, '2026-03-01 10:00:00-04');
        $this->registeredAt($civil, $surveying, '2026-03-01 09:00:00-04');
        $this->registeredAt($systems, $networks, '2026-08-01 08:00:00-04', $this->otherPeriodId);
        $this->registeredAt($civil, $surveying, '2026-08-01 13:00:00-04', $this->otherPeriodId);

        $this->actAsAdministrator();

        $this->assertSame(
            ['Ingenieria de Sistemas/Redes', 'Ingenieria Civil/Topografia'],
            $this->labels($this->getJson($this->url())->assertOk()->json('data'))
        );
        $this->assertSame(
            ['Ingenieria Civil/Topografia', 'Ingenieria de Sistemas/Redes'],
            $this->labels($this->getJson($this->url([], $this->otherPeriodId))->assertOk()->json('data'))
        );
    }

    public function test_la_asignacion_recien_confirmada_aparece_primera_en_el_listado(): void
    {
        $systems = $this->career('Ingenieria de Sistemas', 'SIS');
        $civil = $this->career('Ingenieria Civil', 'CIV');
        $surveying = $this->offer($civil, 'Topografia', '2008012');
        $networks = $this->offer($systems, 'Redes', '2008010');
        $this->registeredAt($civil, $surveying, '2026-01-10 08:00:00-04');
        $this->registeredAt($systems, $networks, '2026-01-10 09:00:00-04');
        $calculus = $this->subject('Calculo I', '2008057');

        $this->actAsAdministrator();

        $this->postJson("/api/administracion/carreras/{$systems->id_carrera}/materias", [
            'id_materia' => $calculus->id_materia,
            'id_periodo' => $this->periodId,
        ])->assertCreated();

        $rows = $this->getJson($this->url())->assertOk()->assertJsonCount(3, 'data')->json('data');

        $this->assertSame($systems->id_carrera, $rows[0]['id_carrera']);
        $this->assertSame($calculus->id_materia, $rows[0]['id_materia']);
        $this->assertSame(RecordStatus::ACTIVE, $rows[0]['estado']);
        $this->assertSame(
            ['Ingenieria de Sistemas/Calculo I', 'Ingenieria de Sistemas/Redes', 'Ingenieria Civil/Topografia'],
            $this->labels($rows)
        );
    }

    public function test_filtra_por_carrera(): void
    {
        $systems = $this->career('Ingenieria de Sistemas', 'SIS');
        $civil = $this->career('Ingenieria Civil', 'CIV');
        $this->offer($systems, 'Redes', '2008010');
        $this->offer($civil, 'Topografia', '2008012');

        $this->actAsAdministrator();

        $rows = $this->getJson($this->url(['id_carrera' => $civil->id_carrera]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->json('data');

        $this->assertSame($civil->id_carrera, $rows[0]['id_carrera']);
        $this->assertSame('Topografia', $rows[0]['materia']['nombre']);
    }

    public function test_una_carrera_activa_sin_ofertas_en_el_periodo_devuelve_lista_vacia(): void
    {
        $empty = $this->career('Carrera Nueva', 'NEW');
        $systems = $this->career('Ingenieria de Sistemas', 'SIS');
        $this->offer($systems, 'Redes', '2008010');
        $this->offer($empty, 'Solo en otro periodo', '2008050', RecordStatus::ACTIVE, $this->otherPeriodId);

        $this->actAsAdministrator();

        $this->getJson($this->url(['id_carrera' => $empty->id_carrera]))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_meta_carreras_incluye_todas_las_activas_aunque_no_tengan_ofertas(): void
    {
        $systems = $this->career('Ingenieria de Sistemas', 'SIS');
        $civil = $this->career('Ingenieria Civil', 'CIV');
        $this->career('Carrera Sin Ofertas', 'VAC');
        $this->offer($systems, 'Redes', '2008010');
        $this->offer($civil, 'Topografia', '2008012');

        $this->actAsAdministrator();

        $careers = $this->getJson($this->url())->assertOk()->json('meta.carreras');

        $this->assertSame(
            ['Carrera Sin Ofertas', 'Ingenieria Civil', 'Ingenieria de Sistemas'],
            array_column($careers, 'nombre')
        );
        $this->assertSame(['id_carrera', 'nombre', 'codigo', 'id_facultad'], array_keys($careers[0]));
    }

    public function test_meta_carreras_incluye_inactivas_solo_si_tienen_ofertas_en_el_periodo(): void
    {
        $withOffering = $this->career('Carrera Archivada Con Oferta', 'ARC');
        $withoutOffering = $this->career('Carrera Archivada Sin Oferta', 'ARD');
        $otherPeriodOnly = $this->career('Carrera Archivada Otro Periodo', 'ARE');
        $this->offer($withOffering, 'Historia', '2008020');
        $this->offer($otherPeriodOnly, 'Latin', '2008021', RecordStatus::ACTIVE, $this->otherPeriodId);
        Career::query()
            ->whereIn('id_carrera', [$withOffering->id_carrera, $withoutOffering->id_carrera, $otherPeriodOnly->id_carrera])
            ->update(['estado' => RecordStatus::INACTIVE]);

        $this->actAsAdministrator();

        $this->assertSame(
            [$withOffering->id_carrera],
            array_column($this->getJson($this->url())->assertOk()->json('meta.carreras'), 'id_carrera')
        );

        $this->assertSame(
            [$otherPeriodOnly->id_carrera],
            array_column(
                $this->getJson($this->url([], $this->otherPeriodId))->assertOk()->json('meta.carreras'),
                'id_carrera'
            )
        );
    }

    public function test_meta_carreras_no_depende_del_filtro_de_la_lista(): void
    {
        $systems = $this->career('Ingenieria de Sistemas', 'SIS');
        $civil = $this->career('Ingenieria Civil', 'CIV');
        $this->offer($systems, 'Redes', '2008010');
        $this->offer($civil, 'Topografia', '2008012');

        $this->actAsAdministrator();

        $unfiltered = $this->getJson($this->url())->assertOk()->json('meta.carreras');
        $filtered = $this->getJson($this->url(['id_carrera' => $civil->id_carrera]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->json('meta.carreras');

        $this->assertCount(2, $unfiltered);
        $this->assertSame($unfiltered, $filtered);
    }

    public function test_meta_no_se_entrega_a_docente_ni_auxiliar(): void
    {
        foreach ([Role::DOCENTE, Role::AUXILIAR] as $roleName) {
            $this->actingAs($this->accountWithRole($roleName));

            $this->assertNull($this->getJson($this->url())->assertForbidden()->json('meta'));
        }
    }

    public function test_el_numero_de_consultas_no_crece_con_la_cantidad_de_ofertas(): void
    {
        $systems = $this->career('Ingenieria de Sistemas', 'SIS');
        $civil = $this->career('Ingenieria Civil', 'CIV');
        $this->offer($systems, 'Redes', '2008010');

        $this->actAsAdministrator();

        $this->assertCount(1, $this->getJson($this->url())->assertOk()->json('data'));
        $queriesWithOne = $this->countQueries(fn () => $this->getJson($this->url())->assertOk());

        foreach (range(1, 8) as $index) {
            $this->offer($index % 2 === 0 ? $systems : $civil, "Materia {$index}", '20081' . $index . '0');
        }

        $this->assertCount(9, $this->getJson($this->url())->assertOk()->json('data'));
        $queriesWithMany = $this->countQueries(fn () => $this->getJson($this->url())->assertOk());

        $this->assertSame($queriesWithOne, $queriesWithMany);
    }

    public function test_el_periodo_es_obligatorio_y_no_se_asume_el_vigente(): void
    {
        $this->actAsAdministrator();

        $this->getJson(self::URL)
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_periodo');
    }

    /**
     * @dataProvider invalidPeriodIds
     */
    public function test_id_periodo_invalido_responde_422_y_nunca_500(string $periodId): void
    {
        $this->actAsAdministrator();

        $this->getJson(self::URL . '?id_periodo=' . rawurlencode($periodId))
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_periodo');
    }

    public function test_un_periodo_inactivo_responde_422(): void
    {
        $this->actAsAdministrator();

        Period::query()->whereKey($this->otherPeriodId)->update(['estado' => RecordStatus::INACTIVE]);

        $this->getJson($this->url([], $this->otherPeriodId))
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_periodo');
    }

    /**
     * @dataProvider invalidCareerIds
     */
    public function test_id_carrera_invalido_responde_422_y_nunca_500(string $careerId): void
    {
        $this->actAsAdministrator();

        $this->getJson($this->url(['id_carrera' => $careerId]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_carrera');
    }

    public function test_id_carrera_en_el_limite_de_int4_es_valido_y_no_encuentra_nada(): void
    {
        $this->actAsAdministrator();

        $this->getJson($this->url(['id_carrera' => 2147483647]))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_sin_token_responde_401(): void
    {
        $this->getJson($this->url())->assertUnauthorized();
    }

    public function test_docente_y_auxiliar_reciben_403_incluso_con_filtros_invalidos(): void
    {
        foreach ([Role::DOCENTE, Role::AUXILIAR] as $roleName) {
            $this->actingAs($this->accountWithRole($roleName));

            $this->getJson($this->url())->assertForbidden();
            $this->getJson(self::URL . '?id_carrera=abc&id_periodo=xyz')->assertForbidden();
        }
    }

    public function test_administrador_inactivo_recibe_403(): void
    {
        $administrator = $this->accountWithRole(Role::ADMINISTRADOR);
        $administrator->forceFill(['estado' => User::ESTADO_INACTIVO])->save();

        $this->actingAs($administrator)->getJson($this->url())->assertForbidden();
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

    public function invalidPeriodIds(): array
    {
        return [
            'no numerico' => ['abc'],
            'decimal' => ['1.5'],
            'cero' => ['0'],
            'negativo' => ['-3'],
            'inexistente' => ['999999'],
            'primer valor sobre int4' => ['2147483648'],
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
