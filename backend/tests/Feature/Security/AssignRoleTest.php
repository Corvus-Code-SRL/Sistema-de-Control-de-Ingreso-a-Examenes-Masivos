<?php

namespace Tests\Feature\Security;

use App\Models\Role;
use App\Models\User;
use App\Support\RecordStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsAcademicCatalog;
use Tests\Concerns\SeedsSecurityAccounts;
use Tests\TestCase;

class AssignRoleTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsAcademicCatalog;
    use SeedsSecurityAccounts;

    private User $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedSecurityAccounts();
        $this->account = User::factory()->create();
    }

    private function roleUrl(?User $user = null): string
    {
        return '/api/usuarios/' . ($user ?? $this->account)->id_usuario . '/rol';
    }

    private function activeRoleCount(User $user): int
    {
        return DB::table('usuario_rol')
            ->where('id_usuario', $user->id_usuario)
            ->whereNull('fecha_fin')
            ->count();
    }

    private function auditEntries(string $operation): Collection
    {
        return DB::table('log')
            ->join('accion', 'accion.id_accion', '=', 'log.id_accion')
            ->where('accion.operacion', $operation)
            ->where('log.tabla_afectada', 'usuario_rol')
            ->get(['log.*']);
    }

    public function test_asigna_uno_de_los_roles_definidos_a_una_cuenta_sin_rol(): void
    {
        $this->postJson($this->roleUrl(), ['id_rol' => $this->roleId(Role::DOCENTE)])
            ->assertOk()
            ->assertJsonPath('data.nombre_rol', Role::DOCENTE)
            ->assertJsonPath('mensaje', 'Rol asignado correctamente.');

        $this->assertDatabaseHas('usuario_rol', [
            'id_usuario' => $this->account->id_usuario,
            'id_rol'     => $this->roleId(Role::DOCENTE),
            'fecha_fin'  => null,
        ]);
    }

    public function test_al_modificar_el_rol_cierra_el_anterior_y_deja_uno_solo_vigente(): void
    {
        $this->postJson($this->roleUrl(), ['id_rol' => $this->roleId(Role::AUXILIAR)])->assertOk();
        $this->postJson($this->roleUrl(), ['id_rol' => $this->roleId(Role::DOCENTE)])->assertOk();

        $this->assertSame(1, $this->activeRoleCount($this->account));

        $previous = DB::table('usuario_rol')
            ->where('id_usuario', $this->account->id_usuario)
            ->where('id_rol', $this->roleId(Role::AUXILIAR))
            ->first();

        // El historial conserva el rol anterior: se cierra, no se borra.
        $this->assertNotNull($previous);
        $this->assertNotNull($previous->fecha_fin);
    }

    public function test_el_nuevo_rol_se_reconoce_en_la_siguiente_consulta(): void
    {
        $this->giveRole($this->account, Role::AUXILIAR);

        $this->postJson($this->roleUrl(), ['id_rol' => $this->roleId(Role::DOCENTE)])->assertOk();

        $this->getJson($this->roleUrl())
            ->assertOk()
            ->assertJsonPath('data.nombre_rol', Role::DOCENTE);
    }

    public function test_registra_en_la_bitacora_quien_asigno_y_el_rol_anterior(): void
    {
        $this->postJson($this->roleUrl(), ['id_rol' => $this->roleId(Role::AUXILIAR)])->assertOk();
        $this->postJson($this->roleUrl(), ['id_rol' => $this->roleId(Role::DOCENTE)])->assertOk();

        $assignment = $this->auditEntries('ASIGNAR_ROL')->sole();
        $this->assertSame($this->administratorId, $assignment->id_usuario);
        $this->assertNull($assignment->antiguo_valor);
        $this->assertNotNull($assignment->fecha_hora);

        $change = $this->auditEntries('MODIFICAR')->sole();
        $this->assertSame($this->administratorId, $change->id_usuario);
        $this->assertSame(Role::AUXILIAR, json_decode($change->antiguo_valor, true)['nombre_rol']);
        $this->assertSame(Role::DOCENTE, json_decode($change->nuevo_valor, true)['nombre_rol']);
    }

    public function test_no_registra_nada_si_el_rol_no_cambia(): void
    {
        $this->postJson($this->roleUrl(), ['id_rol' => $this->roleId(Role::DOCENTE)])->assertOk();
        $this->postJson($this->roleUrl(), ['id_rol' => $this->roleId(Role::DOCENTE)])->assertOk();

        $this->assertSame(1, DB::table('usuario_rol')->where('id_usuario', $this->account->id_usuario)->count());
        $this->assertCount(0, $this->auditEntries('MODIFICAR'));
    }

    public function test_rechaza_la_operacion_sin_rol_seleccionado(): void
    {
        $this->postJson($this->roleUrl(), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['id_rol' => 'Debe seleccionar un rol.']);
    }

    public function test_solo_permite_roles_definidos_y_activos(): void
    {
        $this->postJson($this->roleUrl(), ['id_rol' => 9999])
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_rol');

        DB::table('rol')->where('nombre_rol', Role::AUXILIAR)->update(['estado' => RecordStatus::INACTIVE]);

        $this->postJson($this->roleUrl(), ['id_rol' => $this->roleId(Role::AUXILIAR)])
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_rol');

        $this->assertSame(0, $this->activeRoleCount($this->account));
    }

    public function test_asignar_rol_no_habilita_una_cuenta_deshabilitada(): void
    {
        $disabled = User::factory()->inactivo()->create();

        $this->postJson($this->roleUrl($disabled), ['id_rol' => $this->roleId(Role::DOCENTE)])->assertOk();

        $this->assertDatabaseHas('usuario', [
            'id_usuario' => $disabled->id_usuario,
            'estado'     => User::ESTADO_INACTIVO,
        ]);
    }

    public function test_el_administrador_no_puede_modificar_su_propio_rol(): void
    {
        $administrator = User::findOrFail($this->administratorId);

        $this->postJson($this->roleUrl($administrator), ['id_rol' => $this->roleId(Role::DOCENTE)])
            ->assertForbidden()
            ->assertJsonPath('message', 'No puede modificar su propio rol. Pídalo a otro administrador.');

        $this->assertSame(Role::ADMINISTRADOR, $administrator->rolActivo()->nombre_rol);
        $this->assertSame(1, $this->activeRoleCount($administrator));
    }

    public function test_un_docente_no_puede_asignar_ni_consultar_roles(): void
    {
        $teacher = User::factory()->create();
        $this->giveRole($teacher, Role::DOCENTE);
        $this->actAs($teacher);

        $this->postJson($this->roleUrl(), ['id_rol' => $this->roleId(Role::AUXILIAR)])->assertForbidden();
        $this->getJson($this->roleUrl())->assertForbidden();

        $this->assertSame(0, $this->activeRoleCount($this->account));
    }

    public function test_una_cuenta_sin_rol_no_puede_usar_la_gestion_de_roles(): void
    {
        $this->actAs(User::factory()->create());

        $this->postJson($this->roleUrl(), ['id_rol' => $this->roleId(Role::DOCENTE)])
            ->assertForbidden()
            ->assertJsonPath('message', 'Solo un Administrador puede gestionar los roles de las cuentas.');
    }

    public function test_consulta_el_rol_actual_de_una_cuenta(): void
    {
        $this->giveRole($this->account, Role::AUXILIAR);

        $this->getJson($this->roleUrl())
            ->assertOk()
            ->assertJsonPath('data.nombre_rol', Role::AUXILIAR);
    }

    public function test_devuelve_null_si_la_cuenta_no_tiene_rol(): void
    {
        $this->getJson($this->roleUrl())
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_responde_404_si_la_cuenta_no_existe_o_el_id_no_es_uuid(): void
    {
        $this->getJson('/api/usuarios/00000000-0000-4000-8000-000000000099/rol')->assertNotFound();
        $this->getJson('/api/usuarios/1/rol')->assertNotFound();
        $this->postJson('/api/usuarios/1/rol', ['id_rol' => $this->roleId(Role::DOCENTE)])->assertNotFound();
    }

    public function test_informa_las_asignaciones_activas_de_un_docente(): void
    {
        $this->seedAcademicCatalog();
        $teacher = User::findOrFail($this->docenteId);

        $examTypeId = DB::table('tipo_examen')->insertGetId([
            'nombre'    => 'Parcial',
            'categoria' => 'REGULAR',
        ], 'id_tipo_examen');

        $upcomingExamId = DB::table('examen')->insertGetId([
            'nombre_examen'      => 'Primer parcial',
            'fecha'              => now()->addWeek()->toDateString(),
            'hora_inicio'        => '08:00',
            'id_tipo_examen'     => $examTypeId,
            'id_carrera'         => $this->sistemasId,
            'id_materia'         => $this->calculoId,
            'id_usuario_docente' => $teacher->id_usuario,
        ], 'id_examen');

        $pastExamId = DB::table('examen')->insertGetId([
            'nombre_examen'      => 'Diagnóstico',
            'fecha'              => now()->subMonth()->toDateString(),
            'hora_inicio'        => '08:00',
            'id_tipo_examen'     => $examTypeId,
            'id_carrera'         => $this->sistemasId,
            'id_materia'         => $this->calculoId,
            'id_usuario_docente' => $teacher->id_usuario,
        ], 'id_examen');

        DB::table('grupo_examen')->insert([
            ['id_grupo' => $this->grupoPropioId, 'id_examen' => $upcomingExamId],
            ['id_grupo' => $this->grupoPropioId, 'id_examen' => $pastExamId],
        ]);

        // Dos grupos del periodo activo en un mismo par; el del periodo anterior no cuenta.
        $this->getJson("/api/usuarios/{$teacher->id_usuario}/asignaciones")
            ->assertOk()
            ->assertJsonPath('data.grupos', 2)
            ->assertJsonPath('data.materias', 1)
            ->assertJsonPath('data.examenes', 1)
            ->assertJsonPath('data.tiene_activas', true);
    }

    public function test_una_cuenta_sin_grupos_no_tiene_asignaciones_activas(): void
    {
        $this->getJson("/api/usuarios/{$this->account->id_usuario}/asignaciones")
            ->assertOk()
            ->assertJsonPath('data.grupos', 0)
            ->assertJsonPath('data.materias', 0)
            ->assertJsonPath('data.examenes', 0)
            ->assertJsonPath('data.tiene_activas', false);
    }
}
