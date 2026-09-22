<?php

namespace Tests\Feature\Academic;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\ActionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class StoreSubjectTest extends TestCase
{
    use DatabaseTransactions;

    private User $adminUser;
    private User $teacherUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(ActionSeeder::class);

        $adminRole = Role::where('nombre_rol', Role::ADMINISTRADOR)->first();
        $teacherRole = Role::where('nombre_rol', Role::DOCENTE)->first();

        $this->adminUser = User::factory()->create();
        $this->adminUser->roles()->attach($adminRole->id_rol, ['fecha_inicio' => now()]);

        $this->teacherUser = User::factory()->create();
        $this->teacherUser->roles()->attach($teacherRole->id_rol, ['fecha_inicio' => now()]);
    }

    private function validData(array $overrides = []): array
    {
        return array_merge([
            'nombre'      => 'Calculo I',
            'codigo'      => '2008057',
            'descripcion' => 'Materia de primer semestre',
        ], $overrides);
    }

    /** @test */
    public function registra_una_materia_con_datos_validos_siendo_administrador()
    {
        $this->actingAs($this->adminUser)
             ->postJson('/api/materias', $this->validData())
             ->assertStatus(201)
             ->assertJsonPath('data.codigo', '2008057')
             ->assertJsonPath('data.estado', 'ACTIVO')
             ->assertJsonPath('mensaje', 'Materia registrada correctamente.');

        $this->assertDatabaseHas('materia', ['codigo' => '2008057']);
    }

    /** @test */
    public function rechaza_un_nombre_vacio()
    {
        $this->actingAs($this->adminUser)
             ->postJson('/api/materias', $this->validData(['nombre' => '']))
             ->assertStatus(422)
             ->assertJsonValidationErrors('nombre');
    }

    /** @test */
    public function rechaza_un_codigo_vacio()
    {
        $this->actingAs($this->adminUser)
             ->postJson('/api/materias', $this->validData(['codigo' => '']))
             ->assertStatus(422)
             ->assertJsonValidationErrors('codigo');
    }

    /** @test */
    public function rechaza_un_nombre_que_excede_la_longitud_maxima()
    {
        $this->actingAs($this->adminUser)
             ->postJson('/api/materias', $this->validData(['nombre' => str_repeat('a', 51)]))
             ->assertStatus(422)
             ->assertJsonValidationErrors('nombre');
    }

    /** @test */
    public function rechaza_un_codigo_con_formato_invalido()
    {
        $this->actingAs($this->adminUser)
             ->postJson('/api/materias', $this->validData(['codigo' => '200805']))
             ->assertStatus(422)
             ->assertJsonValidationErrors('codigo');
    }

    /** @test */
    public function rechaza_un_codigo_duplicado_contra_materia_activa()
    {
        Subject::create([
            'nombre' => 'Otra materia',
            'codigo' => '2008057',
            'estado' => Subject::ESTADO_ACTIVO,
        ]);

        $this->actingAs($this->adminUser)
             ->postJson('/api/materias', $this->validData())
             ->assertStatus(422)
             ->assertJsonValidationErrors('codigo');
    }

    /** @test */
    public function rechaza_un_codigo_duplicado_contra_materia_inactiva()
    {
        Subject::create([
            'nombre' => 'Materia dada de baja',
            'codigo' => '2008057',
            'estado' => Subject::ESTADO_INACTIVO,
        ]);

        $this->actingAs($this->adminUser)
             ->postJson('/api/materias', $this->validData())
             ->assertStatus(422)
             ->assertJsonValidationErrors('codigo');
    }

    /** @test */
    public function asigna_el_estado_activo_automaticamente()
    {
        $this->actingAs($this->adminUser)
             ->postJson('/api/materias', $this->validData())
             ->assertJsonPath('data.estado', 'ACTIVO');

        $this->assertDatabaseHas('materia', ['codigo' => '2008057', 'estado' => 'ACTIVO']);
    }

    /** @test */
    public function ignora_el_estado_enviado_por_el_cliente()
    {
        $this->actingAs($this->adminUser)
             ->postJson('/api/materias', $this->validData(['estado' => 'INACTIVO']))
             ->assertJsonPath('data.estado', 'ACTIVO');
    }

    /** @test */
    public function rechaza_el_registro_si_el_usuario_es_docente()
    {
        $this->actingAs($this->teacherUser)
             ->postJson('/api/materias', $this->validData())
             ->assertStatus(403);

        $this->assertDatabaseMissing('materia', ['codigo' => '2008057']);
    }

    /** @test */
    public function registra_la_operacion_en_la_bitacora()
    {
        $this->actingAs($this->adminUser)
             ->postJson('/api/materias', $this->validData());

        $auditLog = AuditLog::where('tabla_afectada', 'materia')->first();

        $this->assertNotNull($auditLog);
        $this->assertSame($this->adminUser->id_usuario, $auditLog->id_usuario);
        $this->assertSame('2008057', $auditLog->nuevo_valor['codigo']);
    }
}