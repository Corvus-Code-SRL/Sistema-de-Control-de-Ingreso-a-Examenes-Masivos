<?php

namespace Tests\Feature\Academic;

use App\Models\AcademicOffering;
use App\Models\AuditLog;
use App\Models\Group;
use App\Models\Role;
use App\Models\SubjectCareer;
use App\Models\User;
use App\Models\Subject;
use App\Services\Security\AuditLogService;
use App\Support\RecordStatus;
use Database\Seeders\ActionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use LogicException;
use Mockery;
use Tests\Concerns\SeedsAcademicCatalog;
use Tests\TestCase;

/** HU-008: desvincular una materia de una carrera en el período vigente. */
class UnassignSubjectCareerTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsAcademicCatalog;

    private const URL = '/api/catalogo/desvincular-materia';
    private const REACTIVATE_URL = '/api/catalogo/reactivar-materia';

    private User $adminUser;
    private User $teacherUser;
    private User $auxiliarUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, ActionSeeder::class]);
        $this->seedAcademicCatalog();

        $this->adminUser = $this->accountWithRole(Role::ADMINISTRADOR);
        $this->teacherUser = $this->accountWithRole(Role::DOCENTE);
        $this->auxiliarUser = $this->accountWithRole(Role::AUXILIAR);
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

    private function listUrl(array $query = []): string
    {
        return self::URL . '?' . http_build_query($query + [
            'carrera' => $this->sistemasId,
            'periodo' => $this->periodoActivoId,
            'per_page' => 7,
        ]);
    }

    private function unassignUrl(): string
    {
        return self::URL;
    }

    private function unassignPayload(int $materiaId = null, int $periodoId = null): array
    {
        return [
            'id_carrera' => $this->sistemasId,
            'id_periodo' => $periodoId ?? $this->periodoActivoId,
            'id_materia' => $materiaId ?? $this->calculoId,
        ];
    }

    // ---------- CA-1 ----------

    public function test_lista_paginada_del_periodo_vigente_con_grupos_count(): void
    {
        $this->seedExtraPairs(8);
        $this->actingAs($this->adminUser);

        $response = $this->getJson($this->listUrl())->assertOk();

        $response->assertJsonPath('per_page', 7);
        $response->assertJsonPath('page', 1);
        $this->assertSame(11, $response->json('total')); // 3 base + 8 relleno (Sistemas/2026)
        $this->assertCount(7, $response->json('materias'));

        $materias = collect($response->json('materias'));
        $calculo = $materias->firstWhere('id_materia', $this->calculoId);
        $this->assertNotNull($calculo);
        $this->assertSame('MAT-102', $calculo['codigo']);
        $this->assertSame(RecordStatus::ACTIVE, $calculo['estado']);
        $this->assertSame(3, $calculo['grupos_count']); // 2 grupos propios + 1 de otro docente (auxiliar) en el mismo período
    }

    // ---------- CA-2 ----------

    public function test_busqueda_sin_tildes_por_nombre(): void
    {
        $this->actingAs($this->adminUser);

        $response = $this->getJson(self::URL . '?' . http_build_query([
            'carrera' => $this->sistemasId,
            'periodo' => $this->periodoActivoId,
            'buscar' => 'calculo',
        ]))->assertOk();

        $nombres = array_column($response->json('materias'), 'nombre');
        $this->assertContains('Calculo II', $nombres);
        $this->assertNotContains('Base de Datos I', $nombres);
    }

    public function test_busqueda_por_codigo(): void
    {
        $this->seedExtraPairs(8);
        $this->actingAs($this->adminUser);

        $response = $this->getJson(self::URL . '?' . http_build_query([
            'carrera' => $this->sistemasId,
            'periodo' => $this->periodoActivoId,
            'buscar' => 'REL-3',
        ]))->assertOk();

        $codigos = array_column($response->json('materias'), 'codigo');
        $this->assertSame(['REL-3'], $codigos);
    }

    // ---------- CA-4 ----------

        public function test_desvincula_cambia_estado_y_audita(): void
    {
        $this->actingAs($this->adminUser);

        $this->postJson($this->unassignUrl(), $this->unassignPayload($this->materiaInactivaId))
            ->assertOk()
            ->assertJsonPath('id_carrera', $this->sistemasId)
            ->assertJsonPath('id_materia', $this->materiaInactivaId)
            ->assertJsonPath('id_periodo', $this->periodoActivoId)
            ->assertJsonPath('estado', RecordStatus::INACTIVE)
            ->assertJsonPath('grupos_count', 0);

        $this->assertDatabaseHas('oferta_academica', [
            'id_carrera' => $this->sistemasId,
            'id_materia' => $this->materiaInactivaId,
            'id_gestion_periodo' => $this->periodoActivoId,
            'estado' => RecordStatus::INACTIVE,
        ]);

        // La fila sigue existiendo (no se borró).
        $this->assertSame(
            1,
            AcademicOffering::query()
                ->where('id_carrera', $this->sistemasId)
                ->where('id_materia', $this->materiaInactivaId)
                ->where('id_gestion_periodo', $this->periodoActivoId)
                ->count()
        );

        // Las demás ofertas no se tocaron.
        $this->assertDatabaseHas('oferta_academica', [
            'id_carrera' => $this->sistemasId,
            'id_materia' => $this->basesDatosId,
            'id_gestion_periodo' => $this->periodoActivoId,
            'estado' => RecordStatus::ACTIVE,
        ]);

        $log = AuditLog::query()->where('tabla_afectada', 'oferta_academica')->firstOrFail();
        $this->assertSame($this->adminUser->id_usuario, $log->id_usuario);
        $this->assertSame('DESVINCULAR_MATERIA_CARRERA', $log->accion->operacion);
        $this->assertEquals(
            [
                'id_carrera' => $this->sistemasId,
                'id_materia' => $this->materiaInactivaId,
                'id_gestion_periodo' => $this->periodoActivoId,
                'estado' => RecordStatus::ACTIVE,
            ],
            $log->antiguo_valor
        );
        $this->assertEquals(
            [
                'id_carrera' => $this->sistemasId,
                'id_materia' => $this->materiaInactivaId,
                'id_gestion_periodo' => $this->periodoActivoId,
                'estado' => RecordStatus::INACTIVE,
            ],
            $log->nuevo_valor
        );
    }

    // ---------- CA-5 ----------

    public function test_bloquea_si_hay_grupos(): void
    {
        // Calculo II en Sistemas tiene 3 grupos ACTIVOS en el período activo.
        $this->actingAs($this->adminUser);

        $this->postJson($this->unassignUrl(), $this->unassignPayload())
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_materia')
            ->assertJsonPath(
                'errors.id_materia.0',
                'No se puede desvincular: la materia tiene 3 grupos registrados en este período.'
            );

        $this->assertDatabaseHas('oferta_academica', [
            'id_carrera' => $this->sistemasId,
            'id_materia' => $this->calculoId,
            'id_gestion_periodo' => $this->periodoActivoId,
            'estado' => RecordStatus::ACTIVE,
        ]);
    }

    // ---------- CA-6 ----------

    public function test_bloquea_periodo_no_vigente(): void
    {
        $this->actingAs($this->adminUser);

        $this->postJson($this->unassignUrl(), $this->unassignPayload($this->calculoId, $this->periodoAnteriorId))
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_periodo')
            ->assertJsonPath(
                'errors.id_periodo.0',
                'Solo se puede desvincular en el período vigente.'
            );
    }

    // ---------- Extras ----------

    public function test_bloquea_si_ya_esta_inactiva(): void
    {
        DB::table('oferta_academica')
            ->where('id_carrera', $this->sistemasId)
            ->where('id_materia', $this->calculoId)
            ->where('id_gestion_periodo', $this->periodoActivoId)
            ->update(['estado' => RecordStatus::INACTIVE]);

        $this->actingAs($this->adminUser);

        $this->postJson($this->unassignUrl(), $this->unassignPayload())
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_materia')
            ->assertJsonPath(
                'errors.id_materia.0',
                'La materia ya está desvinculada en este período.'
            );
    }

    public function test_solo_administrador_puede_listar_y_desvincular(): void
    {
        foreach ([$this->teacherUser, $this->auxiliarUser] as $user) {
            $this->actingAs($user);
            $this->getJson($this->listUrl())->assertForbidden();
            $this->postJson($this->unassignUrl(), $this->unassignPayload())->assertForbidden();
        }
    }

    public function test_rollback_si_audit_falla(): void
    {
        $this->actingAs($this->adminUser);
        $this->withoutExceptionHandling();

        $mock = Mockery::mock(AuditLogService::class);
        $mock->shouldReceive('registrar')->andThrow(new LogicException('audit falló'));
        $this->app->instance(AuditLogService::class, $mock);

        // Sin grupos para que la validación previa no interfiera: usamos una materia sin grupos.
        $materiaSinGrupos = \App\Models\Subject::create([
            'nombre' => 'Materia Sin Grupos', 'codigo' => 'SIN-001', 'estado' => RecordStatus::ACTIVE,
        ])->id_materia;
        SubjectCareer::create([
            'id_carrera' => $this->sistemasId,
            'id_materia' => $materiaSinGrupos,
            'estado' => RecordStatus::ACTIVE,
        ]);
        DB::table('oferta_academica')->insert([
            'id_carrera' => $this->sistemasId,
            'id_materia' => $materiaSinGrupos,
            'id_gestion_periodo' => $this->periodoActivoId,
            'estado' => RecordStatus::ACTIVE,
        ]);

        try {
            $this->postJson($this->unassignUrl(), $this->unassignPayload($materiaSinGrupos));
            $this->fail('Se esperaba que el audit lanzara.');
        } catch (LogicException $e) {
            $this->assertSame('audit falló', $e->getMessage());
        }

        $this->assertDatabaseHas('oferta_academica', [
            'id_carrera' => $this->sistemasId,
            'id_materia' => $materiaSinGrupos,
            'id_gestion_periodo' => $this->periodoActivoId,
            'estado' => RecordStatus::ACTIVE,
        ]);
        $this->assertSame(
            0,
            AuditLog::query()->where('tabla_afectada', 'oferta_academica')->count()
        );
    }

    public function test_paginacion_7_por_pagina_y_total_correcto(): void
    {
        $this->seedExtraPairs(8); // total 11 en Sistemas/2026
        $this->actingAs($this->adminUser);

        $page1 = $this->getJson($this->listUrl(['page' => 1]))->assertOk();
        $page2 = $this->getJson($this->listUrl(['page' => 2]))->assertOk();

        $this->assertSame(11, $page1->json('total'));
        $this->assertSame(1, $page1->json('page'));
        $this->assertCount(7, $page1->json('materias'));
        $this->assertCount(4, $page2->json('materias'));
        $this->assertSame([], array_intersect(
            array_column($page1->json('materias'), 'id_materia'),
            array_column($page2->json('materias'), 'id_materia')
        ));
    }

    public function test_un_grupo_inactivo_no_cuenta_ni_bloquea(): void
    {
        // Materia Archivada (Sistemas): oferta ACTIVA sin grupos activos.
        Group::create([
            'id_carrera' => $this->sistemasId,
            'id_materia' => $this->materiaInactivaId,
            'num_grupo' => '1',
            'estado' => RecordStatus::INACTIVE,
            'id_usuario_docente' => $this->docenteId,
            'id_gestion_periodo' => $this->periodoActivoId,
        ]);
        $this->actingAs($this->adminUser);

        $materias = collect($this->getJson($this->listUrl())->assertOk()->json('materias'));
        $this->assertSame(0, $materias->firstWhere('id_materia', $this->materiaInactivaId)['grupos_count']);

        $this->postJson($this->unassignUrl(), $this->unassignPayload($this->materiaInactivaId))
            ->assertOk()
            ->assertJsonPath('estado', RecordStatus::INACTIVE);
    }   

    // ---------- Reactivar ----------
    private function inactivateOffer(int $carreraId, int $materiaId, ?int $periodoId = null): void
    {
        DB::table('oferta_academica')
            ->where('id_carrera', $carreraId)
            ->where('id_materia', $materiaId)
            ->where('id_gestion_periodo', $periodoId ?? $this->periodoActivoId)
            ->update(['estado' => RecordStatus::INACTIVE]);
    }

    private function createOfferedSubject(string $code): int
    {
        $id = Subject::create([
            'nombre' => "Materia {$code}", 'codigo' => $code, 'estado' => RecordStatus::ACTIVE,
        ])->id_materia;
        SubjectCareer::create([
            'id_carrera' => $this->sistemasId, 'id_materia' => $id, 'estado' => RecordStatus::ACTIVE,
        ]);
        DB::table('oferta_academica')->insert([
            'id_carrera' => $this->sistemasId,
            'id_materia' => $id,
            'id_gestion_periodo' => $this->periodoActivoId,
            'estado' => RecordStatus::ACTIVE,
        ]);

        return $id;
    }

    public function test_reactiva_cambia_estado_solo_de_esa_oferta_y_audita(): void
    {
        $this->inactivateOffer($this->sistemasId, $this->basesDatosId);
        $this->actingAs($this->adminUser);

        $this->postJson(self::REACTIVATE_URL, $this->unassignPayload($this->basesDatosId))
            ->assertOk()
            ->assertJsonPath('id_materia', $this->basesDatosId)
            ->assertJsonPath('id_periodo', $this->periodoActivoId)
            ->assertJsonPath('estado', RecordStatus::ACTIVE)
            ->assertJsonPath('grupos_count', 1); // Base de Datos I tiene 1 grupo activo en Sistemas

        $this->assertDatabaseHas('oferta_academica', [
            'id_carrera' => $this->sistemasId,
            'id_materia' => $this->basesDatosId,
            'id_gestion_periodo' => $this->periodoActivoId,
            'estado' => RecordStatus::ACTIVE,
        ]);

        $log = AuditLog::query()->where('tabla_afectada', 'oferta_academica')->firstOrFail();
        $this->assertSame($this->adminUser->id_usuario, $log->id_usuario);
        $this->assertSame('REACTIVAR_MATERIA_CARRERA', $log->accion->operacion);
        $this->assertSame(RecordStatus::INACTIVE, $log->antiguo_valor['estado']);
        $this->assertSame(RecordStatus::ACTIVE, $log->nuevo_valor['estado']);
    }

    public function test_reactivar_no_toca_las_demas_ofertas(): void
    {
        $this->inactivateOffer($this->sistemasId, $this->basesDatosId);
        $this->inactivateOffer($this->sistemasId, $this->materiaInactivaId);
        $this->actingAs($this->adminUser);

        $this->postJson(self::REACTIVATE_URL, $this->unassignPayload($this->basesDatosId))->assertOk();

        $this->assertDatabaseHas('oferta_academica', [
            'id_carrera' => $this->sistemasId,
            'id_materia' => $this->materiaInactivaId,
            'id_gestion_periodo' => $this->periodoActivoId,
            'estado' => RecordStatus::INACTIVE,
        ]);
    }

    public function test_reactivar_una_oferta_ya_activa_da_422(): void
    {
        $this->actingAs($this->adminUser);

        $this->postJson(self::REACTIVATE_URL, $this->unassignPayload())
            ->assertStatus(422)
            ->assertJsonPath('errors.id_materia.0', 'La materia ya está activa en este período.');
    }

    public function test_reactivar_en_periodo_no_vigente_da_422(): void
    {
        $this->actingAs($this->adminUser);

        $this->postJson(self::REACTIVATE_URL, $this->unassignPayload($this->calculoId, $this->periodoAnteriorId))
            ->assertStatus(422)
            ->assertJsonPath('errors.id_periodo.0', 'Solo se puede reactivar en el período vigente.');
    }

    public function test_reactivar_una_oferta_inexistente_da_422(): void
    {
        $materiaSinOferta = Subject::create([
            'nombre' => 'Sin oferta', 'codigo' => 'NOF-001', 'estado' => RecordStatus::ACTIVE,
        ])->id_materia;
        $this->actingAs($this->adminUser);

        $this->postJson(self::REACTIVATE_URL, $this->unassignPayload($materiaSinOferta))
            ->assertStatus(422)
            ->assertJsonPath('errors.id_materia.0', 'La materia no está asignada a esta carrera en este período.');
    }

    public function test_reactivar_con_la_materia_inactiva_da_422(): void
    {
        // Materia Archivada: materia INACTIVA con par ACTIVO.
        $this->inactivateOffer($this->sistemasId, $this->materiaInactivaId);
        $this->actingAs($this->adminUser);

        $this->postJson(self::REACTIVATE_URL, $this->unassignPayload($this->materiaInactivaId))
            ->assertStatus(422)
            ->assertJsonPath(
                'errors.id_materia.0',
                'No se puede reactivar: la materia o su asignación a la carrera está inactiva.'
            );
    }

    public function test_reactivar_con_el_par_inactivo_da_422(): void
    {
        // Informática + Base de Datos I: materia ACTIVA con par INACTIVO.
        $this->inactivateOffer($this->informaticaId, $this->basesDatosId);
        $this->actingAs($this->adminUser);

        $this->postJson(self::REACTIVATE_URL, [
            'id_carrera' => $this->informaticaId,
            'id_periodo' => $this->periodoActivoId,
            'id_materia' => $this->basesDatosId,
        ])
            ->assertStatus(422)
            ->assertJsonPath(
                'errors.id_materia.0',
                'No se puede reactivar: la materia o su asignación a la carrera está inactiva.'
            );
    }

    public function test_solo_administrador_puede_reactivar(): void
    {
        $this->inactivateOffer($this->sistemasId, $this->basesDatosId);

        foreach ([$this->teacherUser, $this->auxiliarUser] as $user) {
            $this->actingAs($user);
            $this->postJson(self::REACTIVATE_URL, $this->unassignPayload($this->basesDatosId))->assertForbidden();
        }
    }

    public function test_rollback_al_reactivar_si_audit_falla(): void
    {
        $materia = $this->createOfferedSubject('REA-001');
        $this->inactivateOffer($this->sistemasId, $materia);
        $this->actingAs($this->adminUser);
        $this->withoutExceptionHandling();

        $mock = Mockery::mock(AuditLogService::class);
        $mock->shouldReceive('registrar')->andThrow(new LogicException('audit falló'));
        $this->app->instance(AuditLogService::class, $mock);

        try {
            $this->postJson(self::REACTIVATE_URL, $this->unassignPayload($materia));
            $this->fail('Se esperaba que el audit lanzara.');
        } catch (LogicException $e) {
            $this->assertSame('audit falló', $e->getMessage());
        }

        $this->assertDatabaseHas('oferta_academica', [
            'id_carrera' => $this->sistemasId,
            'id_materia' => $materia,
            'id_gestion_periodo' => $this->periodoActivoId,
            'estado' => RecordStatus::INACTIVE,
        ]);
    }

    public function test_ciclo_completo_desvincular_y_reactivar(): void
    {
        $materia = $this->createOfferedSubject('CIC-001');
        $this->actingAs($this->adminUser);

        $this->postJson(self::URL, $this->unassignPayload($materia))
            ->assertOk()->assertJsonPath('estado', RecordStatus::INACTIVE);
        $this->postJson(self::REACTIVATE_URL, $this->unassignPayload($materia))
            ->assertOk()->assertJsonPath('estado', RecordStatus::ACTIVE);

        $this->assertDatabaseHas('oferta_academica', [
            'id_carrera' => $this->sistemasId,
            'id_materia' => $materia,
            'id_gestion_periodo' => $this->periodoActivoId,
            'estado' => RecordStatus::ACTIVE,
        ]);
        $this->assertSame(
            ['DESVINCULAR_MATERIA_CARRERA', 'REACTIVAR_MATERIA_CARRERA'],
            AuditLog::query()->where('tabla_afectada', 'oferta_academica')->orderBy('id_log')->get()
                ->map(fn ($log) => $log->accion->operacion)->all()
        );
    }   
}
