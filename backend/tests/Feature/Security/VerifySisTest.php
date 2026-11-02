<?php

namespace Tests\Feature\Security;

use App\Models\User;
use App\Services\Security\Contracts\SisGateway;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use Tests\Concerns\SeedsSecurityAccounts;
use Tests\TestCase;

/**
 * GET /api/sis/verificar/{cod_sis} — paso previo al registro de una cuenta (HU-001).
 */
class VerifySisTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsSecurityAccounts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSecurityAccounts();
    }

    /** @test */
    public function devuelve_los_datos_de_una_persona_reconocida_por_el_sis()   // CA 2
    {
        $this->getJson('/api/sis/verificar/202312345')
             ->assertOk()
             ->assertJsonPath('data.nombre', 'Laura')
             ->assertJsonPath('data.paterno', 'Mendoza')
             ->assertJsonPath('data.tipo', 'Docente')
             ->assertJsonStructure(['data' => ['nombre', 'paterno', 'materno', 'tipo', 'facultad']]);
    }

    /** @test */
    public function rechaza_un_codigo_que_ya_tiene_cuenta()                      // CA 4
    {
        User::factory()->create([
            'cod_sis'          => '202312345',
            'nombre'           => 'Juan',
            'apellido_paterno' => 'Perez',
            'apellido_materno' => null,
        ]);

        $this->getJson('/api/sis/verificar/202312345')
             ->assertStatus(422)
             ->assertJsonPath('errors.cod_sis.0', 'Ya existe una cuenta con este código SIS: Juan Perez.');
    }

    /** @test */
    public function el_duplicado_se_detecta_aunque_el_sis_no_responda_y_trae_el_id_de_la_cuenta()
    {
        $existing = User::factory()->create([
            'cod_sis' => '202312345',
            'nombre' => 'Juan',
            'apellido_paterno' => 'Perez',
            'apellido_materno' => null,
        ]);

        $mock = Mockery::mock(SisGateway::class);
        $mock->shouldReceive('estaDisponible')->andReturn(false);
        $this->app->instance(SisGateway::class, $mock);

        $this->getJson('/api/sis/verificar/202312345')
             ->assertStatus(422)
             ->assertJsonPath('errors.cod_sis.0', 'Ya existe una cuenta con este código SIS: Juan Perez.')
             ->assertJsonPath('cuenta_existente.id_usuario', $existing->id_usuario);
    }

    /** @test */
    public function un_codigo_no_reconocido_por_el_sis_responde_sin_datos()      // el SIS es solo una ayuda
    {
        $this->getJson('/api/sis/verificar/999999999')
             ->assertOk()
             ->assertJsonPath('data', null);
    }

    /** @test */
    public function responde_503_si_el_sis_no_esta_disponible()                 // CA 10
    {
        $mock = Mockery::mock(SisGateway::class);
        $mock->shouldReceive('estaDisponible')->andReturn(false);
        $mock->shouldNotReceive('existePersona');
        $this->app->instance(SisGateway::class, $mock);

        $this->getJson('/api/sis/verificar/202312345')
             ->assertStatus(503)
             ->assertJsonPath('message', 'El servicio institucional (SIS) no está disponible. Intente más tarde.');
    }
}
