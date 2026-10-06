<?php

namespace Tests\Feature\Security;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\SeedsSecurityAccounts;
use Tests\TestCase;

/**
 * HU-001: el SIS simulado reconoce al personal administrativo y a un docente, además de los
 * códigos de siempre, para que QA pueda registrar una cuenta de cada tipo.
 */
class SisSimulatedPeopleTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsSecurityAccounts;

    private User $administrator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSecurityAccounts();

        $this->administrator = $this->createAccount(Role::ADMINISTRADOR);
        $this->administrator->forceFill(['cod_sis' => 'ADM0001'])->save();
        $this->actAs($this->administrator);
    }

    public function test_verifica_al_personal_administrativo_ADM0002(): void
    {
        $this->getJson('/api/sis/verificar/ADM0002')
            ->assertOk()
            ->assertJsonPath('data.nombre', 'Camila')
            ->assertJsonPath('data.paterno', 'Ejemplo')
            ->assertJsonPath('data.tipo', 'Funcionario')
            ->assertJsonPath('data.facultad', 'Facultad de Ciencias y Tecnología')
            ->assertJsonStructure(['data' => ['nombre', 'paterno', 'materno', 'tipo', 'facultad']]);
    }

    public function test_verifica_al_docente_10611(): void
    {
        $this->getJson('/api/sis/verificar/10611')
            ->assertOk()
            ->assertJsonPath('data.nombre', 'Mateo')
            ->assertJsonPath('data.paterno', 'Simulado')
            ->assertJsonPath('data.tipo', 'Docente')
            ->assertJsonPath('data.facultad', 'Facultad de Ciencias y Tecnología');
    }

    public function test_el_codigo_administrativo_se_reconoce_en_minusculas_y_con_espacios(): void
    {
        $this->getJson('/api/sis/verificar/' . rawurlencode(' adm0002 '))
            ->assertOk()
            ->assertJsonPath('data.tipo', 'Funcionario');
    }

    public function test_los_codigos_de_siempre_conservan_sus_datos(): void
    {
        foreach (['202312345', '202312346', '202312347', '201900001'] as $code) {
            $this->getJson("/api/sis/verificar/{$code}")
                ->assertOk()
                ->assertJsonPath('data.nombre', 'Laura')
                ->assertJsonPath('data.paterno', 'Mendoza')
                ->assertJsonPath('data.materno', 'Rivas')
                ->assertJsonPath('data.tipo', 'Docente')
                ->assertJsonPath('data.facultad', 'Facultad de Ciencias y Tecnología');
        }
    }

    public function test_un_codigo_parecido_pero_no_reconocido_sigue_rechazandose(): void
    {
        foreach (['ADM0003', '10612', '010611', 'ADM002'] as $code) {
            $this->getJson("/api/sis/verificar/{$code}")->assertStatus(422);
        }
    }

    /**
     * @dataProvider registrablePeople
     */
    public function test_registra_cada_persona_con_ADM0001_como_actor(
        string $code,
        string $first,
        string $last,
        string $email
    ): void {
        $this->postJson('/api/usuarios', [
            'cod_sis' => $code,
            'nombre' => $first,
            'apellido_paterno' => $last,
            'correo' => $email,
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.cod_sis', $code)
            ->assertJsonPath('data.rol', null);

        $account = User::where('cod_sis', $code)->firstOrFail();
        $log = AuditLog::where('tabla_afectada', 'usuario')->where('id_usuario', $this->administrator->id_usuario)->first();

        $this->assertNotNull($log);
        $this->assertSame('ADM0001', $log->usuario->cod_sis);
        $this->assertSame('CREAR', $log->accion->operacion);
        $this->assertSame($account->id_usuario, $log->nuevo_valor['id_usuario']);
        $this->assertDatabaseMissing('usuario_rol', ['id_usuario' => $account->id_usuario]);
    }

    /**
     * @dataProvider registrablePeople
     */
    public function test_registrar_otra_vez_la_misma_persona_devuelve_el_error_de_duplicado(
        string $code,
        string $first,
        string $last,
        string $email
    ): void {
        $payload = ['cod_sis' => $code, 'nombre' => $first, 'apellido_paterno' => $last, 'correo' => $email];

        $this->postJson('/api/usuarios', $payload)->assertStatus(201);

        $this->postJson('/api/usuarios', array_merge($payload, ['correo' => 'otro.' . $email]))
            ->assertStatus(422)
            ->assertJsonPath('errors.cod_sis.0', 'Ya existe una cuenta registrada con este código SIS.');

        $this->getJson("/api/sis/verificar/{$code}")
            ->assertStatus(422)
            ->assertJsonPath('errors.cod_sis.0', "Ya existe una cuenta con este código SIS: {$first} {$last}.");

        $this->assertSame(1, User::where('cod_sis', $code)->count());
    }

    public function registrablePeople(): array
    {
        return [
            'personal administrativo' => ['ADM0002', 'Camila', 'Ejemplo', 'camila.ejemplo@sciem.test'],
            'docente' => ['10611', 'Mateo', 'Simulado', 'mateo.simulado@sciem.test'],
        ];
    }
}
