<?php

namespace Tests\Feature\Exams;

use App\Models\AuditLog;
use App\Models\Classroom;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\SeedsSecurityAccounts;
use Tests\TestCase;

class StoreClassroomTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsSecurityAccounts;

    private array $datosValidos = [
        'nro_aula' => 'Aula 101',
        'capacidad' => 30,
        'ubicacion' => 'Modulo A, primer piso',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedSecurityAccounts();
    }

    /** @test */
    public function registra_un_ambiente_con_datos_validos()
    {
        $this->postJson('/api/ambientes', $this->datosValidos)
            ->assertStatus(201)
            ->assertJsonPath('data.nro_aula', 'Aula 101')
            ->assertJsonPath('data.capacidad', 30)
            ->assertJsonPath('data.ubicacion', 'Modulo A, primer piso')
            ->assertJsonPath('data.activo', true);

        $this->assertDatabaseHas('ambiente', [
            'nro_aula' => 'Aula 101',
            'capacidad' => 30,
            'ubicacion' => 'Modulo A, primer piso',
            'estado' => 'ACTIVO',
        ]);
    }

    /** @test */
    public function registra_el_ambiente_como_activo()
    {
        $this->postJson('/api/ambientes', $this->datosValidos)
            ->assertStatus(201);

        $this->assertDatabaseHas('ambiente', [
            'nro_aula' => 'Aula 101',
            'estado' => 'ACTIVO',
        ]);
    }

    /** @test */
    public function rechaza_un_nro_aula_duplicado()
    {
        Classroom::create([
            'nro_aula' => 'Aula 101',
            'capacidad' => 20,
            'ubicacion' => 'Modulo B',
            'estado' => 'ACTIVO',
        ]);

        $this->postJson('/api/ambientes', $this->datosValidos)
            ->assertStatus(422)
            ->assertJsonValidationErrors('nro_aula');
    }

    /** @test */
    public function rechaza_capacidad_cero()
    {
        $datos = array_merge($this->datosValidos, [
            'capacidad' => 0,
        ]);

        $this->postJson('/api/ambientes', $datos)
            ->assertStatus(422)
            ->assertJsonValidationErrors('capacidad');
    }

    /** @test */
    public function rechaza_campos_obligatorios_vacios()
    {
        $this->postJson('/api/ambientes', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'nro_aula',
                'capacidad',
                'ubicacion',
            ]);
    }

    /** @test */
    public function registra_la_creacion_en_bitacora()
    {
        $this->postJson('/api/ambientes', $this->datosValidos)
            ->assertStatus(201);

        $log = AuditLog::where('tabla_afectada', 'ambiente')->first();

        $this->assertNotNull($log);
        $this->assertSame('Aula 101', $log->nuevo_valor['nro_aula']);
        $this->assertSame(30, $log->nuevo_valor['capacidad']);
        $this->assertSame('ACTIVO', $log->nuevo_valor['estado']);
    }

    /** @test */
    public function rechaza_un_nro_aula_duplicado_con_distinto_case_y_espacios()
    {
        Classroom::create([
            'nro_aula' => 'Aula 1',
            'capacidad' => 20,
            'ubicacion' => 'Modulo B',
            'estado' => 'ACTIVO',
        ]);

        $this->postJson('/api/ambientes', [
            'nro_aula' => 'aula 1 ',
            'capacidad' => 30,
            'ubicacion' => 'Modulo C',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('nro_aula');
    }

    /** @test */
    public function normaliza_los_espacios_del_nro_aula_antes_de_guardar()
    {
        $this->postJson('/api/ambientes', [
            'nro_aula' => '  Aula   105  ',
            'capacidad' => 30,
            'ubicacion' => 'Modulo D',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.nro_aula', 'Aula 105');

        $this->assertDatabaseHas('ambiente', ['nro_aula' => 'Aula 105']);
    }

    /** @test */
    public function rechaza_el_registro_de_un_docente()
    {
        $docente = User::factory()->create();
        $this->giveRole($docente, Role::DOCENTE);
        $this->actAs($docente);

        $this->postJson('/api/ambientes', $this->datosValidos)->assertForbidden();
    }

    /**
     * CA del sprint: registrar 3 ambientes distintos y rechazar un cuarto duplicado.
     *
     * @test
     */
    public function registra_tres_ambientes_distintos_y_rechaza_un_cuarto_duplicado()
    {
        $this->postJson('/api/ambientes', [
            'nro_aula' => 'Aula 101',
            'capacidad' => 30,
            'ubicacion' => 'Modulo A',
        ])->assertStatus(201);

        $this->postJson('/api/ambientes', [
            'nro_aula' => 'Aula 102',
            'capacidad' => 25,
            'ubicacion' => 'Modulo A',
        ])->assertStatus(201);

        $this->postJson('/api/ambientes', [
            'nro_aula' => 'Lab 1',
            'capacidad' => 20,
            'ubicacion' => 'Modulo B',
        ])->assertStatus(201);

        $this->postJson('/api/ambientes', [
            'nro_aula' => 'Aula 101',
            'capacidad' => 40,
            'ubicacion' => 'Modulo C',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('nro_aula');

        $this->assertDatabaseCount('ambiente', 3);
    }
}