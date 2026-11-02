<?php

namespace Tests\Feature\Security;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\SeedsSecurityAccounts;
use Tests\TestCase;

/** GET /api/usuarios: paginado en el servidor, con búsqueda sin tildes y filtro por rol vigente. */
class ListUsersPaginationTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsSecurityAccounts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSecurityAccounts();
    }

    private function names($response): array
    {
        return collect($response->json('data'))->pluck('nombre')->all();
    }

    public function test_pagina_en_el_servidor_y_conserva_el_usuario_actual_en_meta(): void
    {
        User::factory()->count(12)->create();

        $first = $this->getJson('/api/usuarios?per_page=5')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('meta.total', 13)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonPath('meta.id_usuario_actual', $this->administratorId);

        $third = $this->getJson('/api/usuarios?per_page=5&page=3')->assertOk()->assertJsonCount(3, 'data');

        $this->assertNotEmpty(array_intersect(
            [$this->administratorId],
            array_merge(
                $first->json('data.*.id_usuario'),
                $this->getJson('/api/usuarios?per_page=5&page=2')->json('data.*.id_usuario'),
                $third->json('data.*.id_usuario')
            )
        ));
    }

    public function test_la_pagina_por_defecto_es_de_diez_cuentas(): void
    {
        User::factory()->count(14)->create();

        $this->getJson('/api/usuarios')->assertOk()->assertJsonCount(10, 'data')->assertJsonPath('meta.total', 15);
    }

    public function test_busca_por_nombre_sin_distinguir_tildes_ni_mayusculas(): void
    {
        User::factory()->create(['nombre' => 'José Luis', 'apellido_paterno' => 'Ñúñez', 'cod_sis' => '10452']);
        User::factory()->create(['nombre' => 'Maria', 'apellido_paterno' => 'Rojas', 'cod_sis' => '10453']);

        $this->assertSame(['José Luis'], $this->names($this->getJson('/api/usuarios?q=jose')));
        $this->assertSame(['José Luis'], $this->names($this->getJson('/api/usuarios?q=' . urlencode('ÑUÑEZ'))));
        $this->assertSame(['José Luis'], $this->names($this->getJson('/api/usuarios?q=' . urlencode('luis nunez'))));
    }

    public function test_busca_por_codigo_sis_o_codigo_inv(): void
    {
        User::factory()->create(['nombre' => 'Invitado Uno', 'cod_sis' => 'INV4821']);
        User::factory()->create(['nombre' => 'Docente Dos', 'cod_sis' => '10452']);

        $this->assertSame(['Invitado Uno'], $this->names($this->getJson('/api/usuarios?q=inv48')));
        $this->assertSame(['Docente Dos'], $this->names($this->getJson('/api/usuarios?q=10452')));
    }

    public function test_un_comodin_escrito_en_la_busqueda_se_trata_como_texto(): void
    {
        User::factory()->create(['nombre' => 'Ana']);

        $this->getJson('/api/usuarios?q=' . urlencode('%'))->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_filtra_por_rol_vigente_y_por_sin_rol(): void
    {
        $teacher = User::factory()->create(['nombre' => 'Con Rol']);
        $this->giveRole($teacher, Role::DOCENTE);
        User::factory()->create(['nombre' => 'Sin Rol']);

        $byRole = $this->getJson('/api/usuarios?rol=' . $this->roleId(Role::DOCENTE));
        $this->assertSame(['Con Rol'], $this->names($byRole));

        $withoutRole = $this->getJson('/api/usuarios?rol=sin-rol');
        $this->assertSame(['Sin Rol'], $this->names($withoutRole));
    }

    /** Una cuenta con cada rol de la lista y una sin rol; devuelve el nombre de la cuenta por rol. */
    private function accountsByEveryRole(): void
    {
        $accounts = [
            Role::DOCENTE => ['nombre' => 'Cuenta Docente', 'cod_sis' => '10452'],
            Role::AUXILIAR => ['nombre' => 'Cuenta Auxiliar', 'cod_sis' => '202312399'],
            Role::INVITADO => ['nombre' => 'Cuenta Invitada', 'cod_sis' => 'INV4821'],
        ];

        foreach ($accounts as $role => $attributes) {
            $this->giveRole(User::factory()->create($attributes), $role);
        }

        User::factory()->create(['nombre' => 'Cuenta Sin Rol', 'cod_sis' => '10999']);
    }

    public function roleFilterOptions(): array
    {
        return [
            'docente' => [Role::DOCENTE, ['Cuenta Docente']],
            'auxiliar' => [Role::AUXILIAR, ['Cuenta Auxiliar']],
            'docente invitado' => [Role::INVITADO, ['Cuenta Invitada']],
        ];
    }

    /** @dataProvider roleFilterOptions */
    public function test_cada_opcion_del_filtro_de_rol_devuelve_solo_sus_cuentas(string $role, array $expected): void
    {
        $this->accountsByEveryRole();

        $names = $this->names($this->getJson('/api/usuarios?per_page=50&rol=' . $this->roleId($role)));

        $this->assertEqualsCanonicalizing($expected, $names);
    }

    public function test_el_filtro_administrador_devuelve_la_cuenta_administradora(): void
    {
        $this->accountsByEveryRole();

        $response = $this->getJson('/api/usuarios?per_page=50&rol=' . $this->roleId(Role::ADMINISTRADOR));

        $this->assertSame([$this->administratorId], $response->json('data.*.id_usuario'));
    }

    public function test_sin_rol_devuelve_las_cuentas_que_no_tienen_ninguno(): void
    {
        $this->accountsByEveryRole();

        $names = $this->names($this->getJson('/api/usuarios?per_page=50&rol=sin-rol'));

        $this->assertSame(['Cuenta Sin Rol'], $names);
    }

    public function test_sin_filtro_de_rol_devuelve_todas_las_cuentas(): void
    {
        $this->accountsByEveryRole();

        $this->getJson('/api/usuarios?per_page=50')->assertOk()->assertJsonPath('meta.total', 5);
    }

    public function test_el_filtro_de_rol_se_combina_con_la_busqueda_y_la_paginacion(): void
    {
        foreach (['Ana', 'Beto', 'Carla'] as $name) {
            $this->giveRole(User::factory()->create(['nombre' => $name]), Role::DOCENTE);
        }
        User::factory()->create(['nombre' => 'Dario']);

        $teacher = $this->roleId(Role::DOCENTE);

        $this->assertSame(['Beto'], $this->names($this->getJson("/api/usuarios?rol={$teacher}&q=beto")));
        $this->assertSame([], $this->names($this->getJson("/api/usuarios?rol={$teacher}&q=dario")));
        $this->assertSame(['Dario'], $this->names($this->getJson('/api/usuarios?rol=sin-rol&q=dario')));

        $second = $this->getJson("/api/usuarios?rol={$teacher}&per_page=2&page=2")
            ->assertOk()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.last_page', 2);

        $this->assertCount(1, $second->json('data'));
    }

    public function test_un_rol_cerrado_no_cuenta_para_el_filtro(): void
    {
        $account = User::factory()->create(['nombre' => 'Fue Auxiliar']);
        \Illuminate\Support\Facades\DB::table('usuario_rol')->insert([
            'id_usuario' => $account->id_usuario,
            'id_rol' => $this->roleId(Role::AUXILIAR),
            'fecha_inicio' => '2026-01-10 08:00:00',
            'fecha_fin' => '2026-02-10 08:00:00',
        ]);

        $this->assertSame([], $this->names($this->getJson('/api/usuarios?rol=' . $this->roleId(Role::AUXILIAR))));
        $this->assertContains('Fue Auxiliar', $this->names($this->getJson('/api/usuarios?rol=sin-rol&per_page=50')));
    }

    public function test_marca_las_cuentas_de_docente_invitado(): void
    {
        User::factory()->create(['cod_sis' => 'INV4821']);
        User::factory()->create(['cod_sis' => '10452']);

        $accounts = collect($this->getJson('/api/usuarios?per_page=50')->json('data'))->keyBy('cod_sis');

        $this->assertTrue($accounts['INV4821']['es_invitado']);
        $this->assertFalse($accounts['10452']['es_invitado']);
    }

    /** @dataProvider invalidQueries */
    public function test_rechaza_parametros_invalidos(string $query): void
    {
        $this->getJson("/api/usuarios?{$query}")->assertStatus(422);
    }

    public function invalidQueries(): array
    {
        return [['page=0'], ['per_page=0'], ['per_page=51'], ['page=abc'], ['rol=todos'], ['rol=1;drop'], ['rol=1234567890']];
    }

    public function test_solo_un_administrador_lista_las_cuentas(): void
    {
        $this->actAs($this->createAccount(Role::DOCENTE));

        $this->getJson('/api/usuarios')->assertForbidden();
    }
}
