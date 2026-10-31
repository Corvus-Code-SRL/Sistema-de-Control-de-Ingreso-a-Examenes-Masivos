<?php

namespace Tests\Feature\Security;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsSecurityAccounts;
use Tests\TestCase;

class ListUsersTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsSecurityAccounts;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedSecurityAccounts();
    }

    public function test_lista_las_cuentas_con_su_rol_vigente_o_sin_rol(): void
    {
        $withoutRole = User::factory()->create(['apellido_paterno' => 'Aguilar']);
        $teacher = User::factory()->create(['apellido_paterno' => 'Bustos']);
        $this->giveRole($teacher, Role::DOCENTE);

        $response = $this->getJson('/api/usuarios')
            ->assertOk()
            ->assertJsonPath('meta.id_usuario_actual', $this->administratorId);

        $accounts = collect($response->json('data'))->keyBy('id_usuario');

        $this->assertNull($accounts[$withoutRole->id_usuario]['rol']);
        $this->assertSame(Role::DOCENTE, $accounts[$teacher->id_usuario]['rol']['nombre_rol']);
        $this->assertSame(Role::ADMINISTRADOR, $accounts[$this->administratorId]['rol']['nombre_rol']);
        $this->assertArrayNotHasKey('contrasenia', $accounts[$teacher->id_usuario]);
    }

    public function test_un_rol_cerrado_no_aparece_como_vigente(): void
    {
        $account = User::factory()->create();
        DB::table('usuario_rol')->insert([
            'id_usuario'   => $account->id_usuario,
            'id_rol'       => $this->roleId(Role::AUXILIAR),
            'fecha_inicio' => '2026-01-10 08:00:00',
            'fecha_fin'    => '2026-02-10 08:00:00',
        ]);

        $accounts = collect($this->getJson('/api/usuarios')->json('data'))->keyBy('id_usuario');

        $this->assertNull($accounts[$account->id_usuario]['rol']);
    }

    public function test_la_cantidad_de_consultas_no_crece_con_la_cantidad_de_cuentas(): void
    {
        $this->giveRole(User::factory()->create(), Role::DOCENTE);
        $queriesForFewAccounts = $this->countQueries(fn () => $this->getJson('/api/usuarios')->assertOk());

        User::factory()->count(8)->create()->each(fn (User $user) => $this->giveRole($user, Role::AUXILIAR));
        $queriesForManyAccounts = $this->countQueries(fn () => $this->getJson('/api/usuarios')->assertOk());

        $this->assertSame($queriesForFewAccounts, $queriesForManyAccounts);
    }

    public function test_solo_un_administrador_puede_listar_las_cuentas(): void
    {
        $teacher = User::factory()->create();
        $this->giveRole($teacher, Role::DOCENTE);
        $this->actAs($teacher);

        $this->getJson('/api/usuarios')->assertForbidden();
    }

    private function countQueries(callable $request): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $request();

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
