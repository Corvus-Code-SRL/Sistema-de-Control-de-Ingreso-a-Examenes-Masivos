<?php

namespace Tests\Feature\Academic;

use App\Models\Role;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** HU-006: catálogo de solo lectura de Administración, con búsqueda por código o nombre. */
class AdminSubjectListTest extends TestCase
{
    use DatabaseTransactions;

    private const URL = '/api/materias/administracion';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
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

    private function subject(string $name, string $code, string $status = Subject::ESTADO_ACTIVO): Subject
    {
        return Subject::create(['nombre' => $name, 'codigo' => $code, 'estado' => $status]);
    }

    /** @return array<int, string> códigos devueltos por el catálogo */
    private function searchCodes(string $term): array
    {
        return collect(
            $this->actingAs($this->accountWithRole(Role::ADMINISTRADOR))
                ->getJson(self::URL . '?q=' . rawurlencode($term))
                ->assertOk()
                ->json('data')
        )->pluck('codigo')->all();
    }

    public function test_administrador_consulta_el_catalogo_con_codigo_nombre_y_estado(): void
    {
        $this->subject('Calculo I', '2008057');

        $this->actingAs($this->accountWithRole(Role::ADMINISTRADOR))
            ->getJson(self::URL)
            ->assertOk()
            ->assertJsonPath('data.0.nombre', 'Calculo I')
            ->assertJsonPath('data.0.codigo', '2008057')
            ->assertJsonPath('data.0.estado', Subject::ESTADO_ACTIVO);
    }

    public function test_sin_token_responde_401(): void
    {
        $this->getJson(self::URL)->assertUnauthorized();
    }

    public function test_docente_y_auxiliar_reciben_403(): void
    {
        foreach ([Role::DOCENTE, Role::AUXILIAR] as $roleName) {
            $this->actingAs($this->accountWithRole($roleName))
                ->getJson(self::URL)
                ->assertForbidden();
        }
    }

    public function test_cuenta_sin_rol_recibe_403(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson(self::URL)
            ->assertForbidden();
    }

    public function test_incluye_materias_sin_relacion_materia_carrera_activas_e_inactivas(): void
    {
        $this->subject('Fisica I', '2008058');
        $this->subject('Materia Antigua', '2008060', Subject::ESTADO_INACTIVO);

        $this->actingAs($this->accountWithRole(Role::ADMINISTRADOR))
            ->getJson(self::URL)
            ->assertOk()
            ->assertJsonFragment(['codigo' => '2008058', 'estado' => Subject::ESTADO_ACTIVO])
            ->assertJsonFragment(['codigo' => '2008060', 'estado' => Subject::ESTADO_INACTIVO]);
    }

    public function test_cada_materia_aparece_una_sola_vez(): void
    {
        $subject = $this->subject('Programacion I', '2008061');

        $matches = array_filter(
            $this->actingAs($this->accountWithRole(Role::ADMINISTRADOR))
                ->getJson(self::URL)
                ->assertOk()
                ->json('data'),
            fn (array $item): bool => $item['id_materia'] === $subject->id_materia
        );

        $this->assertCount(1, $matches);
    }

    public function test_busca_por_codigo_con_coincidencia_parcial(): void
    {
        $this->subject('Calculo I', '2008057');
        $this->subject('Fisica I', '3009001');

        $this->assertSame(['2008057'], $this->searchCodes('2008'));
        $this->assertSame(['3009001'], $this->searchCodes('9001'));
    }

    public function test_busca_por_nombre_sin_distinguir_mayusculas(): void
    {
        $this->subject('Calculo I', '2008057');
        $this->subject('Fisica I', '3009001');

        $this->assertSame(['3009001'], $this->searchCodes('fISICA'));
    }

    public function test_calculo_sin_tilde_encuentra_calculo_con_tilde(): void
    {
        $this->subject('Cálculo I', '2008057');
        $this->subject('Fisica I', '3009001');

        $this->assertSame(['2008057'], $this->searchCodes('calculo'));
        $this->assertSame(['2008057'], $this->searchCodes('CALCULO'));
    }

    public function test_calculo_en_mayusculas_con_tilde_tambien_lo_encuentra(): void
    {
        $this->subject('Cálculo I', '2008057');
        $this->subject('Fisica I', '3009001');

        $this->assertSame(['2008057'], $this->searchCodes('CÁLCULO'));
        $this->assertSame(['2008057'], $this->searchCodes('cálculo'));
    }

    public function test_la_busqueda_normaliza_tambien_enie_y_dieresis(): void
    {
        $this->subject('DISEÑO DE SOFTWARE', '4001001');
        $this->subject('Lingüística', '4001002');

        $this->assertSame(['4001001'], $this->searchCodes('diseno'));
        $this->assertSame(['4001001'], $this->searchCodes('diseño'));
        $this->assertSame(['4001002'], $this->searchCodes('linguistica'));
    }

    public function test_un_porcentaje_o_guion_bajo_se_busca_como_texto_literal(): void
    {
        $this->subject('Estadistica', '5001001');
        $this->subject('Probabilidad 100%', '5001002');
        $this->subject('Taller_Redes', '5001003');

        $this->assertSame(['5001002'], $this->searchCodes('%'));
        $this->assertSame(['5001003'], $this->searchCodes('_'));
        $this->assertSame([], $this->searchCodes('\\'));
        $this->assertSame([], $this->searchCodes('Esta%dis'));
    }

    public function test_busqueda_sin_coincidencias_devuelve_lista_vacia(): void
    {
        $this->subject('Calculo I', '2008057');

        $this->assertSame([], $this->searchCodes('zzzz'));
    }

    public function test_busqueda_vacia_devuelve_todo_el_catalogo(): void
    {
        $this->subject('Calculo I', '2008057');
        $this->subject('Fisica I', '3009001');

        $this->assertCount(2, $this->searchCodes(''));
    }

    public function test_busqueda_demasiado_larga_responde_422_y_nunca_500(): void
    {
        $this->actingAs($this->accountWithRole(Role::ADMINISTRADOR))
            ->getJson(self::URL . '?q=' . str_repeat('a', 51))
            ->assertStatus(422)
            ->assertJsonValidationErrors('q');
    }

    public function test_un_arreglo_en_q_responde_422(): void
    {
        $this->actingAs($this->accountWithRole(Role::ADMINISTRADOR))
            ->getJson(self::URL . '?q[]=a')
            ->assertStatus(422);
    }

    public function test_docente_con_busqueda_sigue_recibiendo_403_antes_que_422(): void
    {
        $this->actingAs($this->accountWithRole(Role::DOCENTE))
            ->getJson(self::URL . '?q=' . str_repeat('a', 51))
            ->assertForbidden();
    }
}
