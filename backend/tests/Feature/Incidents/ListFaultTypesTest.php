<?php

namespace Tests\Feature\Incidents;

use App\Models\IncidentType;
use App\Models\Role;
use App\Models\User;
use App\Services\Security\UserRoleService;
use Database\Seeders\IncidentTypeSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsSecurityAccounts;
use Tests\TestCase;

/**
 * GET /api/tipos-falta: catálogo de tipos de falta para Docente, Auxiliar y Administrador.
 */
class ListFaultTypesTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsSecurityAccounts;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedSecurityAccounts();
        $this->seed(IncidentTypeSeeder::class);
    }

    public function test_sin_token_responde_401(): void
    {
        $this->actAsGuest();

        $this->getJson('/api/tipos-falta')->assertUnauthorized();
    }

    public function test_una_cuenta_sin_rol_recibe_403(): void
    {
        $this->actAs($this->createAccount());

        $this->getJson('/api/tipos-falta')->assertForbidden();
    }

    public function test_un_rol_que_no_es_docente_auxiliar_ni_administrador_recibe_403(): void
    {
        $role = DB::table('rol')->insertGetId(
            ['nombre_rol' => 'Observador', 'estado' => 'ACTIVO'],
            'id_rol'
        );
        $user = $this->createAccount();
        DB::table('usuario_rol')->insert([
            'id_usuario' => $user->id_usuario,
            'id_rol' => $role,
            'fecha_inicio' => '2026-01-10 08:00:00',
        ]);
        $this->actAs($user);

        $this->getJson('/api/tipos-falta')->assertForbidden();
    }

    public function test_una_cuenta_inactiva_recibe_403_y_su_token_se_revoca(): void
    {
        $user = $this->createAccount(Role::DOCENTE, User::ESTADO_INACTIVO);
        $token = $user->createToken('prueba')->plainTextToken;

        $this->asToken($token)->getJson('/api/tipos-falta')
            ->assertForbidden()
            ->assertJsonPath('motivo', 'cuenta_inactiva');

        $this->asToken($token)->getJson('/api/tipos-falta')->assertUnauthorized();
    }

    public function test_una_cuenta_con_contrasenia_temporal_pendiente_recibe_403(): void
    {
        $user = $this->createAccount(Role::DOCENTE);
        DB::table('usuario')->where('id_usuario', $user->id_usuario)->update(['debe_cambiar_contrasenia' => true]);
        $token = $user->createToken('prueba')->plainTextToken;

        $this->asToken($token)->getJson('/api/tipos-falta')
            ->assertForbidden()
            ->assertJsonPath('motivo', 'cambio_de_clave_pendiente');
    }

    /** @return array<string, array{0: string}> */
    public function allowedRoles(): array
    {
        return [
            'docente' => [Role::DOCENTE],
            'auxiliar' => [Role::AUXILIAR],
            'administrador' => [Role::ADMINISTRADOR],
        ];
    }

    /** @dataProvider allowedRoles */
    public function test_los_roles_permitidos_reciben_200(string $role): void
    {
        $this->actAs($this->createAccount($role));

        $this->getJson('/api/tipos-falta')->assertOk()->assertJsonCount(4, 'data');
    }

    public function test_devuelve_los_cuatro_tipos_ordenados_por_nombre_con_su_forma(): void
    {
        $this->actAs($this->createAccount(Role::DOCENTE));

        $response = $this->getJson('/api/tipos-falta')->assertOk();

        $this->assertSame(
            ['Conducta indebida', 'Copia en examen', 'Material no permitido', 'Suplantación de identidad'],
            collect($response->json('data'))->pluck('nombre')->all()
        );

        $response->assertJsonStructure(['data' => [['id_falta', 'nombre', 'descripcion']]]);
        $this->assertSame(
            ['id_falta', 'nombre', 'descripcion'],
            array_keys($response->json('data.0'))
        );
        $this->assertIsInt($response->json('data.0.id_falta'));
        $this->assertSame(
            'Comportamiento que altera el orden o desacata las indicaciones del examen.',
            $response->json('data.0.descripcion')
        );
    }

    public function test_incluye_cualquier_tipo_del_catalogo_sin_filtrar(): void
    {
        IncidentType::create(['nombre' => 'Aaa nuevo tipo']);
        $this->actAs($this->createAccount(Role::AUXILIAR));

        $names = collect($this->getJson('/api/tipos-falta')->assertOk()->json('data'))->pluck('nombre');

        $this->assertCount(5, $names);
        $this->assertSame('Aaa nuevo tipo', $names->first());
    }

    public function test_con_el_catalogo_vacio_responde_una_lista_vacia(): void
    {
        DB::table('tipo_falta')->delete();
        $this->actAs($this->createAccount(Role::DOCENTE));

        $this->getJson('/api/tipos-falta')->assertOk()->assertExactJson(['data' => []]);
    }

    public function test_has_any_active_role_exige_cuenta_activa_y_rol_vigente_entre_los_indicados(): void
    {
        $roles = app(UserRoleService::class);
        $allowed = [Role::DOCENTE, Role::AUXILIAR];

        $teacher = $this->createAccount(Role::DOCENTE);
        $assistant = $this->createAccount(Role::AUXILIAR);
        $administrator = $this->createAccount(Role::ADMINISTRADOR);
        $inactiveTeacher = $this->createAccount(Role::DOCENTE, User::ESTADO_INACTIVO);
        $closedRole = $this->createAccount(Role::DOCENTE);
        DB::table('usuario_rol')->where('id_usuario', $closedRole->id_usuario)->update(['fecha_fin' => now()]);

        $this->assertTrue($roles->hasAnyActiveRole($teacher->id_usuario, $allowed));
        $this->assertTrue($roles->hasAnyActiveRole($assistant->id_usuario, $allowed));
        $this->assertTrue($roles->hasAnyActiveRole($administrator->id_usuario, [Role::ADMINISTRADOR]));

        foreach ([$administrator, $this->createAccount(), $inactiveTeacher, $closedRole] as $account) {
            $this->assertFalse($roles->hasAnyActiveRole($account->id_usuario, $allowed), $account->id_usuario);
        }

        $this->assertFalse($roles->hasAnyActiveRole(null, $allowed));
        $this->assertFalse($roles->hasAnyActiveRole($teacher->id_usuario, []));
    }

    /** Cada petición con token resuelve la sesión desde cero, como en producción. */
    private function asToken(string $token): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }
}
