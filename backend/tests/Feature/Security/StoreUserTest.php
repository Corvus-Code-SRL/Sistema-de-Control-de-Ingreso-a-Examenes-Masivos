<?php

namespace Tests\Feature\Security;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\Security\Contracts\SisGateway;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Mockery;
use Monolog\Handler\TestHandler;
use Tests\Concerns\SeedsSecurityAccounts;
use Tests\TestCase;

class StoreUserTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsSecurityAccounts;

    private array $datosValidos = [
        'cod_sis'          => '202312345',
        'nombre'           => 'Juan Carlos',
        'apellido_paterno' => 'Perez',
        'apellido_materno' => 'Garcia',
        'correo'           => 'juan.perez@est.umss.edu',
        'contrasenia_temporal' => 'Temporal2026',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSecurityAccounts();
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

        $newAccountId = User::where('cod_sis', '202312345')->value('id_usuario');

        $this->assertNotNull($newAccountId);
        $this->assertDatabaseMissing('usuario_rol', ['id_usuario' => $newAccountId]);
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
    public function registra_un_cod_sis_que_el_sis_no_reconoce()
    {
        $datos = array_merge($this->datosValidos, ['cod_sis' => '201999999']);

        $this->postJson('/api/usuarios', $datos)
             ->assertStatus(201)
             ->assertJsonPath('data.cod_sis', '201999999');
    }

    /** @test */
    public function registra_aunque_el_sis_no_este_disponible()
    {
        $mock = Mockery::mock(SisGateway::class);
        $mock->shouldReceive('estaDisponible')->andReturn(false);
        $mock->shouldNotReceive('existePersona');
        $this->app->instance(SisGateway::class, $mock);

        $this->postJson('/api/usuarios', $this->datosValidos)
             ->assertStatus(201);
    }

    /** @test */
    public function la_bitacora_no_guarda_la_contrasenia()
    {
        $this->postJson('/api/usuarios', $this->datosValidos);

        $log = AuditLog::where('tabla_afectada', 'usuario')->first();

        $this->assertNotNull($log);
        $this->assertArrayNotHasKey('contrasenia', $log->nuevo_valor);
    }

    /** @test */
    public function guarda_la_contrasenia_temporal_hasheada_y_marca_el_cambio_pendiente()
    {
        $this->postJson('/api/usuarios', $this->datosValidos)
             ->assertStatus(201);

        $account = User::where('cod_sis', '202312345')->firstOrFail();

        $this->assertNotSame('Temporal2026', $account->contrasenia);
        $this->assertTrue(Hash::check('Temporal2026', $account->contrasenia));
        $this->assertTrue($account->debe_cambiar_contrasenia);
    }

    /** @test */
    public function ni_la_contrasenia_ni_su_hash_aparecen_en_la_respuesta_ni_en_los_logs()
    {
        $handler = new TestHandler();
        Log::driver()->getLogger()->pushHandler($handler);

        $response = $this->postJson('/api/usuarios', $this->datosValidos)->assertStatus(201);

        $hash = User::where('cod_sis', '202312345')->value('contrasenia');
        $auditRows = json_encode(DB::table('log')->get());
        $applicationLog = json_encode($handler->getRecords());

        foreach (['Temporal2026', $hash] as $secret) {
            $this->assertStringNotContainsString($secret, $response->getContent());
            $this->assertStringNotContainsString($secret, $auditRows);
            $this->assertStringNotContainsString($secret, $applicationLog);
        }
    }

    /** @test */
    public function una_contrasenia_rechazada_tampoco_se_devuelve()
    {
        $datos = array_merge($this->datosValidos, ['contrasenia_temporal' => 'corta']);

        $response = $this->postJson('/api/usuarios', $datos)
             ->assertStatus(422)
             ->assertJsonValidationErrors('contrasenia_temporal');

        $this->assertStringNotContainsString('corta', $response->getContent());
    }

    /** @test */
    public function exige_la_contrasenia_temporal()
    {
        $datos = $this->datosValidos;
        unset($datos['contrasenia_temporal']);

        $this->postJson('/api/usuarios', $datos)
             ->assertStatus(422)
             ->assertJsonValidationErrors('contrasenia_temporal');

        $this->assertDatabaseMissing('usuario', ['cod_sis' => '202312345']);
    }

    /** @test */
    public function acepta_ocho_caracteres_en_la_contrasenia_temporal()
    {
        $datos = array_merge($this->datosValidos, ['contrasenia_temporal' => 'abcd1234']);

        $this->postJson('/api/usuarios', $datos)->assertStatus(201);
    }

    /** @test */
    public function la_contrasenia_temporal_conserva_los_espacios_de_los_extremos()
    {
        $datos = array_merge($this->datosValidos, ['contrasenia_temporal' => ' Temporal2026 ']);

        $this->postJson('/api/usuarios', $datos)->assertStatus(201);

        $hash = User::where('cod_sis', '202312345')->value('contrasenia');

        $this->assertTrue(Hash::check(' Temporal2026 ', $hash));
    }

    /** @test */
    public function acepta_apellidos_de_60_caracteres_y_rechaza_los_de_61()
    {
        $sixty = str_repeat('a', 60);
        $sixtyOne = str_repeat('a', 61);

        $this->postJson('/api/usuarios', array_merge($this->datosValidos, [
            'apellido_paterno' => $sixty,
            'apellido_materno' => $sixty,
        ]))->assertStatus(201)->assertJsonPath('data.apellido_paterno', $sixty);

        $this->assertSame($sixty, User::where('cod_sis', '202312345')->value('apellido_materno'));

        foreach (['apellido_paterno', 'apellido_materno'] as $field) {
            $this->postJson('/api/usuarios', array_merge($this->datosValidos, [
                'cod_sis' => '202400001',
                'correo' => 'otra.persona@est.umss.edu',
                $field => $sixtyOne,
            ]))->assertStatus(422)->assertJsonValidationErrors($field);
        }
    }

    /** @test */
    public function la_cuenta_registrada_inicia_sesion_con_la_temporal_y_se_le_exige_cambiarla()
    {
        $this->postJson('/api/usuarios', $this->datosValidos)->assertStatus(201);

        $account = User::where('cod_sis', '202312345')->firstOrFail();
        $roleId = $this->roleId('Docente');

        $this->postJson("/api/usuarios/{$account->id_usuario}/rol", ['id_rol' => $roleId])->assertOk();

        $this->actAsGuest();

        $this->postJson('/api/auth/login', ['cod_sis' => '202312345', 'password' => 'Temporal2026'])
             ->assertOk()
             ->assertJsonPath('data.rol.nombre_rol', 'Docente')
             ->assertJsonPath('data.usuario.debe_cambiar_contrasenia', true);
    }

    /** @test */
    public function sin_rol_la_cuenta_registrada_sigue_sin_poder_iniciar_sesion()
    {
        $this->postJson('/api/usuarios', $this->datosValidos)->assertStatus(201);

        $this->actAsGuest();

        $this->postJson('/api/auth/login', ['cod_sis' => '202312345', 'password' => 'Temporal2026'])
             ->assertStatus(403)
             ->assertJsonPath('motivo', 'sin_rol_vigente');
    }
}
