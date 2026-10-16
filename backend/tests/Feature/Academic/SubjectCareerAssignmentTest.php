<?php

namespace Tests\Feature\Academic;

use App\Models\AcademicOffering;
use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\Career;
use App\Models\Period;
use App\Models\PeriodType;
use App\Models\Role;
use App\Models\Subject;
use App\Models\SubjectCareer;
use App\Models\User;
use App\Support\RecordStatus;
use Database\Seeders\ActionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** HU-006: asignar una materia existente a una carrera en un período (gestion_periodo). */
class SubjectCareerAssignmentTest extends TestCase
{
    use DatabaseTransactions;

    private const NOT_CURRENT_MESSAGE = 'Solo se pueden asignar materias en el período vigente.';

    private User $adminUser;
    private User $teacherUser;
    private Career $career;
    private Subject $subject;
    /** 2/2026: el período vigente, el único donde se puede escribir. */
    private int $periodId;
    /** 1/2026: historial de solo consulta. */
    private int $pastPeriodId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RoleSeeder::class,
            ActionSeeder::class,
        ]);

        $adminRole = Role::where('nombre_rol', Role::ADMINISTRADOR)->firstOrFail();
        $teacherRole = Role::where('nombre_rol', Role::DOCENTE)->firstOrFail();

        $this->adminUser = User::factory()->create();
        $this->adminUser->roles()->attach($adminRole->id_rol, ['fecha_inicio' => now()]);

        $this->teacherUser = User::factory()->create();
        $this->teacherUser->roles()->attach($teacherRole->id_rol, ['fecha_inicio' => now()]);

        $facultyId = DB::table('facultad')->insertGetId([
            'nombre' => 'Facultad de Ciencias y Tecnologia',
            'codigo' => 'FCYT',
            'estado' => RecordStatus::ACTIVE,
        ], 'id_facultad');

        $this->career = Career::create([
            'nombre' => 'Ingenieria de Sistemas',
            'codigo' => 'SIS',
            'estado' => RecordStatus::ACTIVE,
            'id_facultad' => $facultyId,
        ]);

        $this->subject = Subject::create([
            'nombre' => 'Inteligencia Artificial',
            'codigo' => '2008001',
            'descripcion' => 'Materia para HU-006',
            'estado' => RecordStatus::ACTIVE,
        ]);

        $this->pastPeriodId = $this->createPeriod(2026, 1);
        $this->periodId = $this->createPeriod(2026, 2, RecordStatus::ACTIVE, true);
    }

    private function createPeriod(
        int $year,
        int $code,
        string $status = RecordStatus::ACTIVE,
        bool $current = false
    ): int {
        $yearId = AcademicYear::query()->firstOrCreate(
            ['anio' => $year],
            ['estado' => RecordStatus::ACTIVE]
        )->id_gestion;

        return Period::create([
            'id_gestion' => $yearId,
            'id_periodo' => PeriodType::query()->where('codigo', $code)->value('id_periodo'),
            'vigente' => $current,
            'estado' => $status,
        ])->id_gestion_periodo;
    }

    /** Simula el cambio de período vigente: libera el actual antes de fijar el nuevo. */
    private function makeCurrent(int $periodId): void
    {
        Period::query()->where('vigente', true)->update(['vigente' => false]);
        Period::query()->whereKey($periodId)->update(['vigente' => true]);
    }

    private function assignmentUrl(): string
    {
        return "/api/administracion/carreras/{$this->career->id_carrera}/materias";
    }

    private function payload(?int $periodId = null): array
    {
        return [
            'id_materia' => $this->subject->id_materia,
            'id_periodo' => $periodId ?? $this->periodId,
        ];
    }

    private function offeringsOf(int $subjectId): int
    {
        return AcademicOffering::query()->where('id_materia', $subjectId)->count();
    }

    public function test_administrador_puede_asignar_materia_a_carrera_en_un_periodo(): void
    {
        $this->actingAs($this->adminUser)
            ->postJson($this->assignmentUrl(), $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.id_carrera', $this->career->id_carrera)
            ->assertJsonPath('data.id_materia', $this->subject->id_materia)
            ->assertJsonPath('data.id_periodo', $this->periodId)
            ->assertJsonPath('data.estado', RecordStatus::ACTIVE)
            ->assertJsonPath('data.periodo.etiqueta', '2/2026')
            ->assertJsonPath('data.carrera.nombre', 'Ingenieria de Sistemas')
            ->assertJsonPath('data.materia.nombre', 'Inteligencia Artificial')
            ->assertJsonPath('mensaje', 'Materia asignada a la carrera en el período correctamente.');

        $this->assertDatabaseHas('materia_carrera', [
            'id_carrera' => $this->career->id_carrera,
            'id_materia' => $this->subject->id_materia,
            'estado' => RecordStatus::ACTIVE,
        ]);

        $this->assertDatabaseHas('oferta_academica', [
            'id_carrera' => $this->career->id_carrera,
            'id_materia' => $this->subject->id_materia,
            'id_gestion_periodo' => $this->periodId,
            'estado' => RecordStatus::ACTIVE,
        ]);
    }

    public function test_la_bitacora_registra_crear_en_el_par_y_en_la_oferta_con_el_administrador(): void
    {
        $this->actingAs($this->adminUser)
            ->postJson($this->assignmentUrl(), $this->payload())
            ->assertCreated();

        $pairRows = AuditLog::query()->where('tabla_afectada', 'materia_carrera')->get();
        $offeringRows = AuditLog::query()->where('tabla_afectada', 'oferta_academica')->get();

        $this->assertCount(1, $pairRows);
        $this->assertSame($this->adminUser->id_usuario, $pairRows[0]->id_usuario);
        $this->assertSame('CREAR', $pairRows[0]->accion->operacion);
        $this->assertNull($pairRows[0]->antiguo_valor);
        $this->assertEquals(
            [
                'id_carrera' => $this->career->id_carrera,
                'id_materia' => $this->subject->id_materia,
                'estado' => RecordStatus::ACTIVE,
            ],
            $pairRows[0]->nuevo_valor
        );

        $this->assertCount(1, $offeringRows);
        $this->assertSame($this->adminUser->id_usuario, $offeringRows[0]->id_usuario);
        $this->assertSame('CREAR', $offeringRows[0]->accion->operacion);
        $this->assertNull($offeringRows[0]->antiguo_valor);
        $this->assertEquals(
            [
                'id_carrera' => $this->career->id_carrera,
                'id_materia' => $this->subject->id_materia,
                'id_gestion_periodo' => $this->periodId,
                'estado' => RecordStatus::ACTIVE,
            ],
            $offeringRows[0]->nuevo_valor
        );
    }

    public function test_cada_asignacion_la_registra_otro_administrador_con_su_propia_cuenta(): void
    {
        $otherAdmin = User::factory()->create();
        $otherAdmin->roles()->attach(
            Role::where('nombre_rol', Role::ADMINISTRADOR)->value('id_rol'),
            ['fecha_inicio' => now()]
        );

        $this->actingAs($otherAdmin)
            ->postJson($this->assignmentUrl(), $this->payload())
            ->assertCreated();

        $this->assertSame(
            [$otherAdmin->id_usuario, $otherAdmin->id_usuario],
            AuditLog::query()
                ->whereIn('tabla_afectada', ['materia_carrera', 'oferta_academica'])
                ->pluck('id_usuario')
                ->all()
        );
    }

    public function test_la_misma_materia_se_ofrece_de_nuevo_en_otro_periodo_reutilizando_el_par(): void
    {
        $this->actingAs($this->adminUser);

        $this->postJson($this->assignmentUrl(), $this->payload($this->periodId))->assertCreated();

        // Al cambiar el período vigente, la misma materia vuelve a poder ofrecerse.
        $nextPeriodId = $this->createPeriod(2027, 1);
        $this->makeCurrent($nextPeriodId);

        $this->postJson($this->assignmentUrl(), $this->payload($nextPeriodId))
            ->assertCreated()
            ->assertJsonPath('data.periodo.etiqueta', '1/2027');

        $this->assertSame(
            1,
            SubjectCareer::query()
                ->where('id_carrera', $this->career->id_carrera)
                ->where('id_materia', $this->subject->id_materia)
                ->count()
        );
        $this->assertSame(2, $this->offeringsOf($this->subject->id_materia));

        // El par se audita una sola vez; cada oferta, la suya.
        $this->assertSame(1, AuditLog::query()->where('tabla_afectada', 'materia_carrera')->count());
        $this->assertSame(2, AuditLog::query()->where('tabla_afectada', 'oferta_academica')->count());
    }

    public function test_con_un_par_activo_existente_solo_crea_la_oferta(): void
    {
        SubjectCareer::create([
            'id_carrera' => $this->career->id_carrera,
            'id_materia' => $this->subject->id_materia,
            'estado' => RecordStatus::ACTIVE,
        ]);

        $this->actingAs($this->adminUser)
            ->postJson($this->assignmentUrl(), $this->payload())
            ->assertCreated();

        $this->assertSame(1, $this->offeringsOf($this->subject->id_materia));
        $this->assertSame(0, AuditLog::query()->where('tabla_afectada', 'materia_carrera')->count());
        $this->assertSame(1, AuditLog::query()->where('tabla_afectada', 'oferta_academica')->count());
    }

    public function test_rechaza_la_misma_materia_dos_veces_en_el_mismo_periodo(): void
    {
        $this->actingAs($this->adminUser);

        $this->postJson($this->assignmentUrl(), $this->payload())->assertCreated();

        $this->postJson($this->assignmentUrl(), $this->payload())
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_materia');

        $this->assertSame(1, $this->offeringsOf($this->subject->id_materia));
        $this->assertSame(1, AuditLog::query()->where('tabla_afectada', 'oferta_academica')->count());
    }

    public function test_una_oferta_inactiva_en_el_mismo_periodo_tambien_bloquea_la_asignacion(): void
    {
        SubjectCareer::create([
            'id_carrera' => $this->career->id_carrera,
            'id_materia' => $this->subject->id_materia,
            'estado' => RecordStatus::ACTIVE,
        ]);
        AcademicOffering::create([
            'id_carrera' => $this->career->id_carrera,
            'id_materia' => $this->subject->id_materia,
            'id_gestion_periodo' => $this->periodId,
            'estado' => RecordStatus::INACTIVE,
        ]);

        $this->actingAs($this->adminUser)
            ->postJson($this->assignmentUrl(), $this->payload())
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_materia');

        $this->assertSame(
            RecordStatus::INACTIVE,
            AcademicOffering::query()->where('id_materia', $this->subject->id_materia)->value('estado')
        );
        $this->assertSame(0, AuditLog::query()->where('tabla_afectada', 'oferta_academica')->count());
    }

    public function test_rechaza_un_par_materia_carrera_inactivo_sin_reactivarlo(): void
    {
        SubjectCareer::create([
            'id_carrera' => $this->career->id_carrera,
            'id_materia' => $this->subject->id_materia,
            'estado' => RecordStatus::INACTIVE,
        ]);

        $this->actingAs($this->adminUser)
            ->postJson($this->assignmentUrl(), $this->payload())
            ->assertStatus(422)
            ->assertJsonPath('errors.id_materia.0', 'El vínculo materia-carrera está inactivo.');

        $this->assertSame(
            RecordStatus::INACTIVE,
            SubjectCareer::query()
                ->where('id_carrera', $this->career->id_carrera)
                ->where('id_materia', $this->subject->id_materia)
                ->value('estado')
        );
        $this->assertSame(0, $this->offeringsOf($this->subject->id_materia));
        $this->assertNoAudit();
    }

    public function test_docente_no_puede_asignar_materia_a_carrera(): void
    {
        $this->actingAs($this->teacherUser)
            ->postJson($this->assignmentUrl(), $this->payload())
            ->assertForbidden();

        $this->assertNoAssignmentWritten();
    }

    public function test_rechaza_asignacion_sin_materia(): void
    {
        $this->actingAs($this->adminUser)
            ->postJson($this->assignmentUrl(), ['id_periodo' => $this->periodId])
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_materia');

        $this->assertNoAudit();
    }

    public function test_rechaza_asignacion_con_materia_inexistente(): void
    {
        $this->actingAs($this->adminUser)
            ->postJson($this->assignmentUrl(), ['id_materia' => 999999, 'id_periodo' => $this->periodId])
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_materia');

        $this->assertNoAudit();
    }

    public function test_rechaza_asignacion_sin_periodo_sin_asumir_el_vigente(): void
    {
        Period::query()->whereKey($this->periodId)->update(['vigente' => true]);

        $this->actingAs($this->adminUser)
            ->postJson($this->assignmentUrl(), ['id_materia' => $this->subject->id_materia])
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_periodo');

        $this->assertNoAssignmentWritten();
    }

    public function test_rechaza_un_periodo_inexistente(): void
    {
        $this->actingAs($this->adminUser)
            ->postJson($this->assignmentUrl(), $this->payload(999999))
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_periodo');

        $this->assertNoAssignmentWritten();
    }

    public function test_rechaza_un_periodo_inactivo(): void
    {
        $inactive = $this->createPeriod(2027, 1, RecordStatus::INACTIVE);

        $this->actingAs($this->adminUser)
            ->postJson($this->assignmentUrl(), $this->payload($inactive))
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_periodo');

        $this->assertNoAssignmentWritten();
    }

    public function test_rechaza_asignar_en_un_periodo_pasado_que_no_es_el_vigente(): void
    {
        $this->actingAs($this->adminUser)
            ->postJson($this->assignmentUrl(), $this->payload($this->pastPeriodId))
            ->assertStatus(422)
            ->assertJsonPath('errors.id_periodo.0', self::NOT_CURRENT_MESSAGE);

        $this->assertNoAssignmentWritten();
    }

    public function test_rechaza_asignar_si_ningun_periodo_es_el_vigente(): void
    {
        Period::query()->update(['vigente' => false]);

        $this->actingAs($this->adminUser);

        foreach ([$this->periodId, $this->pastPeriodId] as $periodId) {
            $this->postJson($this->assignmentUrl(), $this->payload($periodId))
                ->assertStatus(422)
                ->assertJsonPath('errors.id_periodo.0', self::NOT_CURRENT_MESSAGE);
        }

        $this->assertNoAssignmentWritten();
    }

    public function test_un_duplicado_en_un_periodo_pasado_responde_por_el_periodo_sin_escribir(): void
    {
        SubjectCareer::create([
            'id_carrera' => $this->career->id_carrera,
            'id_materia' => $this->subject->id_materia,
            'estado' => RecordStatus::ACTIVE,
        ]);
        AcademicOffering::create([
            'id_carrera' => $this->career->id_carrera,
            'id_materia' => $this->subject->id_materia,
            'id_gestion_periodo' => $this->pastPeriodId,
            'estado' => RecordStatus::ACTIVE,
        ]);

        $this->actingAs($this->adminUser)
            ->postJson($this->assignmentUrl(), $this->payload($this->pastPeriodId))
            ->assertStatus(422)
            ->assertJsonPath('errors.id_periodo.0', self::NOT_CURRENT_MESSAGE)
            ->assertJsonMissingValidationErrors('id_materia');

        $this->assertSame(1, $this->offeringsOf($this->subject->id_materia));
        $this->assertNoAudit();
    }

    /**
     * @dataProvider invalidPeriodIds
     *
     * @param mixed $periodId
     */
    public function test_rechaza_un_id_de_periodo_invalido_con_422_y_nunca_500($periodId): void
    {
        $this->actingAs($this->adminUser)
            ->postJson($this->assignmentUrl(), [
                'id_materia' => $this->subject->id_materia,
                'id_periodo' => $periodId,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_periodo');

        $this->assertNoAssignmentWritten();
    }

    public function test_rechaza_asignacion_si_materia_o_carrera_estan_inactivas(): void
    {
        $this->subject->update(['estado' => RecordStatus::INACTIVE]);

        $this->actingAs($this->adminUser)
            ->postJson($this->assignmentUrl(), $this->payload())
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_materia');

        $this->subject->update(['estado' => RecordStatus::ACTIVE]);
        $this->career->update(['estado' => RecordStatus::INACTIVE]);

        $this->actingAs($this->adminUser)
            ->postJson($this->assignmentUrl(), $this->payload())
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_carrera');

        $this->assertNoAssignmentWritten();
    }

    public function test_las_asignaciones_no_se_pueden_editar_ni_eliminar(): void
    {
        $pair = "{$this->assignmentUrl()}/{$this->subject->id_materia}";

        $this->actingAs($this->adminUser);

        foreach (['PUT', 'PATCH', 'DELETE'] as $method) {
            foreach ([$this->assignmentUrl(), $pair, '/api/administracion/asignaciones'] as $url) {
                $this->assertContains(
                    $this->json($method, $url, ['estado' => RecordStatus::INACTIVE])->getStatusCode(),
                    [404, 405],
                    "{$method} {$url} no debería existir"
                );
            }
        }

        $this->assertNoAssignmentWritten();
    }

    public function test_administrador_lista_unicamente_carreras_activas(): void
    {
        $inactiveCareer = Career::create([
            'nombre' => 'Carrera Archivada',
            'codigo' => 'ARC',
            'estado' => RecordStatus::INACTIVE,
            'id_facultad' => $this->career->id_facultad,
        ]);

        $careerIds = collect(
            $this->actingAs($this->adminUser)->getJson('/api/administracion/carreras')->assertOk()->json('data')
        )->pluck('id_carrera')->all();

        $this->assertContains($this->career->id_carrera, $careerIds);
        $this->assertNotContains($inactiveCareer->id_carrera, $careerIds);
    }

    public function test_materias_asignables_dependen_del_periodo(): void
    {
        $other = Career::create([
            'nombre' => 'Ingenieria Civil',
            'codigo' => 'CIV',
            'estado' => RecordStatus::ACTIVE,
            'id_facultad' => $this->career->id_facultad,
        ]);

        $this->actingAs($this->adminUser);
        $this->postJson($this->assignmentUrl(), $this->payload($this->periodId))->assertCreated();

        $ids = fn (Career $career, int $periodId): array => collect(
            $this->getJson(
                "/api/administracion/carreras/{$career->id_carrera}/materias-asignables?id_periodo={$periodId}"
            )->assertOk()->json('data')
        )->pluck('id_materia')->all();

        // En el vigente ya está ofrecida para Sistemas; al cambiar de vigente vuelve a estar disponible.
        $this->assertNotContains($this->subject->id_materia, $ids($this->career, $this->periodId));
        $this->assertContains($this->subject->id_materia, $ids($other, $this->periodId));

        $nextPeriodId = $this->createPeriod(2027, 1);
        $this->makeCurrent($nextPeriodId);

        $this->assertContains($this->subject->id_materia, $ids($this->career, $nextPeriodId));
    }

    public function test_materias_asignables_rechaza_un_periodo_pasado_o_sin_vigente(): void
    {
        $this->actingAs($this->adminUser);
        $base = "/api/administracion/carreras/{$this->career->id_carrera}/materias-asignables";

        $this->getJson("{$base}?id_periodo={$this->pastPeriodId}")
            ->assertStatus(422)
            ->assertJsonPath('errors.id_periodo.0', self::NOT_CURRENT_MESSAGE);

        Period::query()->update(['vigente' => false]);

        $this->getJson("{$base}?id_periodo={$this->periodId}")
            ->assertStatus(422)
            ->assertJsonPath('errors.id_periodo.0', self::NOT_CURRENT_MESSAGE);
    }

    public function test_listar_las_asignaciones_de_un_periodo_pasado_sigue_funcionando(): void
    {
        SubjectCareer::create([
            'id_carrera' => $this->career->id_carrera,
            'id_materia' => $this->subject->id_materia,
            'estado' => RecordStatus::ACTIVE,
        ]);
        AcademicOffering::create([
            'id_carrera' => $this->career->id_carrera,
            'id_materia' => $this->subject->id_materia,
            'id_gestion_periodo' => $this->pastPeriodId,
            'estado' => RecordStatus::ACTIVE,
        ]);

        $this->actingAs($this->adminUser);

        $this->getJson("/api/administracion/asignaciones?id_periodo={$this->pastPeriodId}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_materia', $this->subject->id_materia)
            ->assertJsonPath('meta.periodo.etiqueta', '1/2026')
            ->assertJsonPath('meta.periodo.vigente', false);

        Period::query()->update(['vigente' => false]);

        $this->getJson("/api/administracion/asignaciones?id_periodo={$this->pastPeriodId}")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_administrador_lista_solo_materias_asignables(): void
    {
        $offeredHere = Subject::create(['nombre' => 'Sistemas Operativos', 'codigo' => '2008002', 'estado' => RecordStatus::ACTIVE]);
        $inactivePair = Subject::create(['nombre' => 'Redes de Computadoras', 'codigo' => '2008003', 'estado' => RecordStatus::ACTIVE]);
        $inactiveSubject = Subject::create(['nombre' => 'Materia Archivada', 'codigo' => '2008004', 'estado' => RecordStatus::INACTIVE]);
        $inactiveOffering = Subject::create(['nombre' => 'Compiladores', 'codigo' => '2008005', 'estado' => RecordStatus::ACTIVE]);

        foreach ([$offeredHere, $inactiveOffering] as $subject) {
            SubjectCareer::create([
                'id_carrera' => $this->career->id_carrera,
                'id_materia' => $subject->id_materia,
                'estado' => RecordStatus::ACTIVE,
            ]);
        }
        AcademicOffering::create([
            'id_carrera' => $this->career->id_carrera,
            'id_materia' => $offeredHere->id_materia,
            'id_gestion_periodo' => $this->periodId,
            'estado' => RecordStatus::ACTIVE,
        ]);
        AcademicOffering::create([
            'id_carrera' => $this->career->id_carrera,
            'id_materia' => $inactiveOffering->id_materia,
            'id_gestion_periodo' => $this->periodId,
            'estado' => RecordStatus::INACTIVE,
        ]);
        SubjectCareer::create([
            'id_carrera' => $this->career->id_carrera,
            'id_materia' => $inactivePair->id_materia,
            'estado' => RecordStatus::INACTIVE,
        ]);

        $ids = collect(
            $this->actingAs($this->adminUser)
                ->getJson(
                    "/api/administracion/carreras/{$this->career->id_carrera}"
                    . "/materias-asignables?id_periodo={$this->periodId}"
                )
                ->assertOk()
                ->json('data')
        )->pluck('id_materia')->all();

        $this->assertSame([$this->subject->id_materia], $ids);
        $this->assertNotContains($offeredHere->id_materia, $ids);
        $this->assertNotContains($inactiveOffering->id_materia, $ids);
        $this->assertNotContains($inactivePair->id_materia, $ids);
        $this->assertNotContains($inactiveSubject->id_materia, $ids);
    }

    public function test_materias_asignables_exige_un_periodo_valido(): void
    {
        $this->actingAs($this->adminUser);
        $base = "/api/administracion/carreras/{$this->career->id_carrera}/materias-asignables";

        foreach (['', '?id_periodo=abc', '?id_periodo=0', '?id_periodo=2147483648', '?id_periodo=999999'] as $query) {
            $this->getJson($base . $query)
                ->assertStatus(422)
                ->assertJsonValidationErrors('id_periodo');
        }
    }

    public function test_docente_no_puede_listar_carreras_administrativas(): void
    {
        $this->actingAs($this->teacherUser)
            ->getJson('/api/administracion/carreras')
            ->assertForbidden();
    }

    public function test_docente_no_puede_listar_materias_asignables(): void
    {
        $this->actingAs($this->teacherUser)
            ->getJson(
                "/api/administracion/carreras/{$this->career->id_carrera}"
                . "/materias-asignables?id_periodo={$this->periodId}"
            )
            ->assertForbidden();
    }

    public function test_rechaza_carrera_no_numerica_con_404(): void
    {
        $this->actingAs($this->adminUser)
            ->postJson('/api/administracion/carreras/abc/materias', $this->payload())
            ->assertNotFound();

        $this->assertNoAssignmentWritten();
    }

    public function test_rechaza_carrera_fuera_de_rango_con_404(): void
    {
        $this->actingAs($this->adminUser)
            ->getJson(
                '/api/administracion/carreras/99999999999/materias-asignables?id_periodo=' . $this->periodId
            )
            ->assertNotFound();
    }

    public function test_rechaza_id_materia_fuera_de_rango(): void
    {
        $this->actingAs($this->adminUser)
            ->postJson($this->assignmentUrl(), ['id_materia' => 99999999999, 'id_periodo' => $this->periodId])
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_materia');

        $this->assertNoAudit();
    }

    /**
     * @dataProvider outOfRangeCareerIds
     */
    public function test_rechaza_carrera_fuera_de_int4_en_materias_asignables(string $careerId): void
    {
        $this->actingAs($this->adminUser)
            ->getJson("/api/administracion/carreras/{$careerId}/materias-asignables?id_periodo={$this->periodId}")
            ->assertNotFound();
    }

    /**
     * @dataProvider outOfRangeCareerIds
     */
    public function test_rechaza_carrera_fuera_de_int4_al_asignar(string $careerId): void
    {
        $this->actingAs($this->adminUser)
            ->postJson("/api/administracion/carreras/{$careerId}/materias", $this->payload())
            ->assertNotFound();

        $this->assertNoAssignmentWritten();
    }

    /**
     * @dataProvider assignmentRoutes
     */
    public function test_rutas_de_asignacion_exigen_token(string $method, string $uri): void
    {
        $this->callAssignmentRoute($method, $uri)->assertUnauthorized();

        $this->assertNoAssignmentWritten();
    }

    /**
     * @dataProvider assignmentRoutes
     */
    public function test_auxiliar_no_puede_usar_rutas_de_asignacion(string $method, string $uri): void
    {
        $assistant = User::factory()->create();
        $assistant->roles()->attach(
            Role::where('nombre_rol', Role::AUXILIAR)->firstOrFail()->id_rol,
            ['fecha_inicio' => now()]
        );

        $this->actingAs($assistant);

        $this->callAssignmentRoute($method, $uri)->assertForbidden();

        $this->assertNoAssignmentWritten();
    }

    /**
     * @dataProvider assignmentRoutes
     */
    public function test_docente_no_puede_usar_ninguna_ruta_de_asignacion(string $method, string $uri): void
    {
        $this->actingAs($this->teacherUser);

        $this->callAssignmentRoute($method, $uri)->assertForbidden();

        $this->assertNoAssignmentWritten();
    }

    /**
     * @dataProvider assignmentRoutes
     */
    public function test_administrador_accede_a_todas_las_rutas_de_asignacion(string $method, string $uri): void
    {
        $this->actingAs($this->adminUser);

        $this->assertContains($this->callAssignmentRoute($method, $uri)->getStatusCode(), [200, 201]);
    }

    public function outOfRangeCareerIds(): array
    {
        return [
            'primer valor sobre int4' => ['2147483648'],
            'diez nueves' => ['9999999999'],
        ];
    }

    public function invalidPeriodIds(): array
    {
        return [
            'no numerico' => ['abc'],
            'decimal' => [1.5],
            'cero' => [0],
            'negativo' => [-3],
            'primer valor sobre int4' => [2147483648],
            'desbordamiento enorme' => ['99999999999999999999'],
            'nulo' => [null],
            'arreglo' => [[1]],
        ];
    }

    public function assignmentRoutes(): array
    {
        return [
            'listar carreras' => ['GET', '/api/administracion/carreras'],
            'listar materias asignables' => [
                'GET',
                '/api/administracion/carreras/{career}/materias-asignables?id_periodo={period}',
            ],
            'asignar materia' => ['POST', '/api/administracion/carreras/{career}/materias'],
            'listar asignaciones' => ['GET', '/api/administracion/asignaciones?id_periodo={period}'],
        ];
    }

    private function callAssignmentRoute(string $method, string $uri): TestResponse
    {
        $payload = $method === 'POST' ? $this->payload() : [];

        return $this->json(
            $method,
            str_replace(
                ['{career}', '{period}'],
                [(string) $this->career->id_carrera, (string) $this->periodId],
                $uri
            ),
            $payload
        );
    }

    private function assertNoAudit(): void
    {
        $this->assertSame(
            0,
            AuditLog::query()->whereIn('tabla_afectada', ['materia_carrera', 'oferta_academica'])->count()
        );
    }

    private function assertNoAssignmentWritten(): void
    {
        $this->assertDatabaseMissing('materia_carrera', ['id_materia' => $this->subject->id_materia]);
        $this->assertDatabaseMissing('oferta_academica', ['id_materia' => $this->subject->id_materia]);
        $this->assertNoAudit();
    }
}
