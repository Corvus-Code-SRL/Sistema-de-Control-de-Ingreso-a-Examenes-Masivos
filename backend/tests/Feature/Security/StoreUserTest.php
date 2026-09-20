<?php

namespace Tests\Feature\Security;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\Security\Contracts\SisGateway;
use Database\Seeders\ActionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class StoreUserTest extends TestCase
{
    use RefreshDatabase;

    private array $datosValidos = [
        'cod_sis'          => '202312345',
        'nombre'           => 'Juan Carlos',
        'apellido_paterno' => 'Perez',
        'apellido_materno' => 'Garcia',
        'correo'           => 'juan.perez@est.umss.edu',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(UserSeeder::class);
        $this->seed(ActionSeeder::class);
    }

    /** @test */
    public function registra_una_cuenta_con_datos_validos()
    {
        $this->postJson('/api/usuarios', $this->datosValidos)
             ->assertStatus(201)
             ->assertJsonPath('data.cod_sis', '202312345')
             ->assertJsonPath('data.estado', 'ACTIVO');

        $this->assertDatabaseHas('usuario', ['cod_sis' => '202312345']);
    }

    /** @test */
    public function la_cuenta_nueva_no_tiene_rol_asignado()   // CA 5
    {
        $this->postJson('/api/usuarios', $this->datosValidos)
             ->assertJsonPath('data.rol', null);

        $this->assertDatabaseCount('usuario_rol', 0);
    }

        /** @test */
    public function nunca_devuelve_la_contrasenia()
    {
        $response = $this->postJson('/api/usuarios', $this->datosValidos);

        $response->assertStatus(201);
        $this->assertArrayNotHasKey('contrasenia', $response->json('data'));
    }

    /** @test */
    public function rechaza_un_cod_sis_duplicado()            // CA 4
    {
        User::factory()->create(['cod_sis' => '202312345']);

        $this->postJson('/api/usuarios', $this->datosValidos)
             ->assertStatus(422)
             ->assertJsonValidationErrors('cod_sis');
    }

    /** @test */
    public function rechaza_un_cod_sis_no_reconocido_por_el_sis()   // CA 3
    {
        $datos = array_merge($this->datosValidos, ['cod_sis' => '999999999']);

        $this->postJson('/api/usuarios', $datos)
             ->assertStatus(422)
             ->assertJsonValidationErrors('cod_sis');
    }

    /** @test */
    public function responde_503_si_el_sis_no_esta_disponible()     // CA 10
    {
        $mock = Mockery::mock(SisGateway::class);
        $mock->shouldReceive('estaDisponible')->andReturn(false);
        $this->app->instance(SisGateway::class, $mock);

        $this->postJson('/api/usuarios', $this->datosValidos)
             ->assertStatus(503);
    }

    /** @test */
    public function la_bitacora_no_guarda_la_contrasenia()
    {
        $this->postJson('/api/usuarios', $this->datosValidos);

        $log = AuditLog::where('tabla_afectada', 'usuario')->first();

        $this->assertNotNull($log);
        $this->assertArrayNotHasKey('contrasenia', $log->nuevo_valor);
    }
}
