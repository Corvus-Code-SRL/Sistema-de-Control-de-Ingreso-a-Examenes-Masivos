<?php

namespace Tests\Feature\Security;

use App\Models\Action;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsSecurityAccounts;
use Tests\TestCase;

/**
 * HU-001: registro de cuentas con el rol elegido, código SIS validado por rol y docentes invitados
 * con código INV generado por el sistema.
 */
class RegisterAccountRulesTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsSecurityAccounts;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-30 09:00:00');
        $this->seedSecurityAccounts();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'cod_sis' => '202312399',
            'nombre' => 'Juan Carlos',
            'apellido_paterno' => 'Perez',
            'apellido_materno' => 'Garcia',
            'correo' => 'juan.perez@umss.edu',
            'contrasenia_temporal' => 'Temporal2026',
        ], $overrides);
    }

    private function guestPayload(array $overrides = []): array
    {
        $data = $this->payload(['correo' => 'invitado@umss.edu', 'sin_codigo_sis' => true]);
        unset($data['cod_sis']);

        return array_merge($data, $overrides);
    }

    /** @dataProvider validCodesByRole */
    public function test_registra_con_un_codigo_valido_para_el_rol(string $role, string $code): void
    {
        $this->postJson('/api/usuarios', $this->payload(['cod_sis' => $code, 'id_rol' => $this->roleId($role)]))
            ->assertCreated()
            ->assertJsonPath('data.cod_sis', $code)
            ->assertJsonPath('data.rol.nombre_rol', $role)
            ->assertJsonPath('data.es_invitado', false);
    }

    public function validCodesByRole(): array
    {
        return [
            'auxiliar de 9 dígitos' => [Role::AUXILIAR, '202312399'],
            'auxiliar legado de 8 dígitos' => [Role::AUXILIAR, '98123456'],
            'administrador' => [Role::ADMINISTRADOR, 'ADM13352440'],
            'docente numérico' => [Role::DOCENTE, '10452'],
            'docente alfanumérico' => [Role::DOCENTE, 'DOC2A9'],
        ];
    }

    /** @dataProvider invalidCodesByRole */
    public function test_rechaza_un_codigo_que_no_cumple_el_formato_del_rol(
        string $role,
        string $code,
        string $field
    ): void {
        $this->postJson('/api/usuarios', $this->payload(['cod_sis' => $code, 'id_rol' => $this->roleId($role)]))
            ->assertStatus(422)
            ->assertJsonValidationErrors($field);

        $this->assertDatabaseMissing('usuario', ['cod_sis' => $code]);
    }

    public function invalidCodesByRole(): array
    {
        return [
            // Bien formado para otro rol: el error es de la combinación y se informa en `id_rol`.
            'auxiliar con año anterior a 1990' => [Role::AUXILIAR, '198912345', 'id_rol'],
            'auxiliar con año futuro' => [Role::AUXILIAR, '202712345', 'id_rol'],
            'auxiliar de 5 dígitos' => [Role::AUXILIAR, '10452', 'id_rol'],
            'administrador sin prefijo de letras' => [Role::ADMINISTRADOR, '202312345', 'id_rol'],
            'administrador sin dígitos' => [Role::ADMINISTRADOR, 'ADMIN', 'id_rol'],
            // Mal formado para cualquier rol: el error es del código.
            'docente demasiado corto' => [Role::DOCENTE, 'AB', 'cod_sis'],
            'docente con símbolos' => [Role::DOCENTE, 'DOC-01', 'cod_sis'],
            'docente con prefijo INV' => [Role::DOCENTE, 'INV4821', 'cod_sis'],
            'administrador con prefijo INV' => [Role::ADMINISTRADOR, 'INV0001', 'cod_sis'],
        ];
    }

    public function test_sin_rol_acepta_cualquiera_de_los_formatos_validos_y_rechaza_el_resto(): void
    {
        foreach (['202312399', '98123456', 'ADM0007', '10452'] as $index => $code) {
            $this->postJson('/api/usuarios', $this->payload([
                'cod_sis' => $code,
                'correo' => "sin.rol.{$index}@umss.edu",
            ]))->assertCreated()->assertJsonPath('data.rol', null);
        }

        $this->postJson('/api/usuarios', $this->payload(['cod_sis' => 'A-1', 'correo' => 'otro@umss.edu']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('cod_sis');
    }

    public function test_guarda_el_codigo_en_mayusculas_y_lo_compara_sin_distinguir_mayusculas(): void
    {
        $this->postJson('/api/usuarios', $this->payload(['cod_sis' => ' adm0009 ']))
            ->assertCreated()
            ->assertJsonPath('data.cod_sis', 'ADM0009');

        $this->postJson('/api/usuarios', $this->payload(['cod_sis' => 'Adm0009', 'correo' => 'otro@umss.edu']))
            ->assertStatus(422)
            ->assertJsonPath('errors.cod_sis.0', 'Ya existe una cuenta registrada con este código SIS.');
    }

    public function test_detecta_un_duplicado_aunque_la_fila_antigua_este_en_minusculas(): void
    {
        DB::table('usuario')->insert($this->rawUser('doc2a9', 'vieja@umss.edu'));

        $this->postJson('/api/usuarios', $this->payload(['cod_sis' => 'DOC2A9', 'correo' => 'nueva@umss.edu']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('cod_sis');
    }

    public function test_el_indice_unico_impide_dos_codigos_iguales_al_ignorar_mayusculas(): void
    {
        DB::table('usuario')->insert($this->rawUser('ZZ1234', 'a@umss.edu'));

        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('usuario')->insert($this->rawUser('zz1234', 'b@umss.edu'));
    }

    public function test_rechaza_correo_duplicado_y_contrasenia_corta(): void
    {
        $this->postJson('/api/usuarios', $this->payload())->assertCreated();

        $this->postJson('/api/usuarios', $this->payload(['cod_sis' => '10452']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('correo');

        $this->postJson('/api/usuarios', $this->payload([
            'cod_sis' => '10453', 'correo' => 'otro@umss.edu', 'contrasenia_temporal' => '1234567',
        ]))->assertStatus(422)->assertJsonValidationErrors('contrasenia_temporal');
    }

    public function test_la_cuenta_nace_obligada_a_cambiar_la_contrasenia_y_con_el_rol_en_la_bitacora(): void
    {
        $response = $this->postJson('/api/usuarios', $this->payload(['id_rol' => $this->roleId(Role::DOCENTE)]))
            ->assertCreated();

        $user = User::findOrFail($response->json('data.id_usuario'));

        $this->assertTrue($user->debe_cambiar_contrasenia);
        $this->assertTrue(Hash::check('Temporal2026', $user->contrasenia));

        $operations = AuditLog::query()
            ->whereIn('tabla_afectada', ['usuario', 'usuario_rol'])
            ->where('id_usuario', $this->administratorId)
            ->orderBy('id_log')
            ->get()
            ->map(fn (AuditLog $log) => Action::whereKey($log->id_accion)->value('operacion') . ':' . $log->tabla_afectada)
            ->all();

        $this->assertSame(['CREAR:usuario', 'ASIGNAR_ROL:usuario_rol'], $operations);
    }

    public function test_cuenta_y_rol_se_guardan_juntos_o_no_se_guarda_nada(): void
    {
        $this->postJson('/api/usuarios', $this->payload(['id_rol' => 99999]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_rol');

        $this->assertDatabaseMissing('usuario', ['cod_sis' => '202312399']);
    }

    public function test_solo_un_administrador_registra_cuentas(): void
    {
        $this->actAs($this->createAccount(Role::DOCENTE));

        $this->postJson('/api/usuarios', $this->payload())->assertForbidden();
        $this->postJson('/api/usuarios', $this->guestPayload())->assertForbidden();
    }

    public function test_docente_invitado_recibe_un_codigo_inv_unico_y_el_rol_invitado(): void
    {
        $response = $this->postJson('/api/usuarios', $this->guestPayload())
            ->assertCreated()
            ->assertJsonPath('data.rol.nombre_rol', Role::INVITADO)
            ->assertJsonPath('data.es_invitado', true);

        $this->assertMatchesRegularExpression('/^INV[1-9]\d{3}$/', $response->json('data.cod_sis'));
    }

    public function test_docente_invitado_puede_quedar_sin_rol_por_ahora(): void
    {
        $this->postJson('/api/usuarios', $this->guestPayload(['id_rol' => null]))
            ->assertCreated()
            ->assertJsonPath('data.rol', null)
            ->assertJsonPath('data.es_invitado', true);
    }

    public function test_docente_invitado_solo_puede_recibir_el_rol_invitado(): void
    {
        $this->postJson('/api/usuarios', $this->guestPayload(['id_rol' => $this->roleId(Role::DOCENTE)]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_rol');

        $this->assertSame(0, DB::table('usuario')->where('cod_sis', 'like', 'INV%')->count());
    }

    public function test_una_cuenta_con_codigo_sis_nunca_recibe_el_rol_invitado(): void
    {
        $this->postJson('/api/usuarios', $this->payload(['id_rol' => $this->roleId(Role::INVITADO)]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_rol');

        $this->assertDatabaseMissing('usuario', ['cod_sis' => '202312399']);
    }

    public function test_un_docente_invitado_no_lleva_codigo_sis(): void
    {
        $this->postJson('/api/usuarios', $this->guestPayload(['cod_sis' => '10452']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('cod_sis');
    }

    public function test_los_codigos_inv_generados_no_se_repiten(): void
    {
        $codes = [];

        for ($i = 0; $i < 25; $i++) {
            $codes[] = $this->postJson('/api/usuarios', $this->guestPayload(['correo' => "inv{$i}@umss.edu"]))
                ->assertCreated()
                ->json('data.cod_sis');
        }

        $this->assertCount(25, array_unique($codes));
    }

    public function test_ofrece_un_codigo_inv_libre_antes_de_registrar(): void
    {
        $code = $this->getJson('/api/usuarios/codigo-invitado')->assertOk()->json('data.cod_sis');

        $this->assertMatchesRegularExpression('/^INV[1-9]\d{3}$/', $code);
        $this->assertDatabaseMissing('usuario', ['cod_sis' => $code]);
    }

    public function test_el_codigo_ofrecido_es_el_unico_libre_cuando_solo_queda_uno(): void
    {
        $this->fillGuestCodesExcept('INV5555');

        $this->getJson('/api/usuarios/codigo-invitado')->assertOk()->assertJsonPath('data.cod_sis', 'INV5555');
    }

    public function test_sin_codigos_libres_el_codigo_ofrecido_informa_el_agotamiento(): void
    {
        $this->fillGuestCodesExcept(null);

        $this->getJson('/api/usuarios/codigo-invitado')->assertStatus(422);
    }

    public function test_solo_un_administrador_obtiene_un_codigo_de_invitado(): void
    {
        $this->actAs($this->createAccount(Role::DOCENTE));

        $this->getJson('/api/usuarios/codigo-invitado')->assertForbidden();
    }

    public function test_registra_al_invitado_con_el_codigo_que_vio_el_administrador(): void
    {
        $this->postJson('/api/usuarios', $this->guestPayload([
            'cod_invitado' => 'inv4821',
            'id_rol' => $this->roleId(Role::INVITADO),
        ]))
            ->assertCreated()
            ->assertJsonPath('data.cod_sis', 'INV4821')
            ->assertJsonPath('data.rol.nombre_rol', Role::INVITADO)
            ->assertJsonPath('data.es_invitado', true);
    }

    public function test_si_el_codigo_mostrado_ya_se_tomo_responde_409_con_un_codigo_libre(): void
    {
        User::factory()->create(['cod_sis' => 'INV4821']);

        $response = $this->postJson('/api/usuarios', $this->guestPayload(['cod_invitado' => 'INV4821']))
            ->assertStatus(409)
            ->assertJsonStructure(['message', 'codigo_sugerido']);

        $suggested = $response->json('codigo_sugerido');

        $this->assertMatchesRegularExpression('/^INV[1-9]\d{3}$/', $suggested);
        $this->assertNotSame('INV4821', $suggested);
        $this->assertDatabaseMissing('usuario', ['correo' => 'invitado@umss.edu']);
        $this->assertDatabaseMissing('usuario', ['cod_sis' => $suggested]);

        $this->postJson('/api/usuarios', $this->guestPayload(['cod_invitado' => $suggested]))
            ->assertCreated()
            ->assertJsonPath('data.cod_sis', $suggested);
    }

    public function test_el_codigo_tomado_se_detecta_aunque_la_fila_antigua_este_en_minusculas(): void
    {
        DB::table('usuario')->insert($this->rawUser('inv4821', 'vieja@umss.edu'));

        $this->postJson('/api/usuarios', $this->guestPayload(['cod_invitado' => 'INV4821']))
            ->assertStatus(409);
    }

    /** @dataProvider invalidGuestCodes */
    public function test_rechaza_un_codigo_de_invitado_con_formato_invalido(string $code): void
    {
        $this->postJson('/api/usuarios', $this->guestPayload(['cod_invitado' => $code]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('cod_invitado');
    }

    public function invalidGuestCodes(): array
    {
        return [['INV123'], ['INV0123'], ['INV12345'], ['ABC1234'], ['4821']];
    }

    public function test_una_cuenta_con_codigo_sis_no_lleva_codigo_de_invitado(): void
    {
        $this->postJson('/api/usuarios', $this->payload(['cod_invitado' => 'INV4821']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('cod_invitado');
    }

    public function test_el_invitado_con_codigo_propio_tampoco_recibe_otro_rol(): void
    {
        $this->postJson('/api/usuarios', $this->guestPayload([
            'cod_invitado' => 'INV4821',
            'id_rol' => $this->roleId(Role::DOCENTE),
        ]))->assertStatus(422)->assertJsonValidationErrors('id_rol');

        $this->assertDatabaseMissing('usuario', ['cod_sis' => 'INV4821']);
    }

    public function test_con_un_solo_codigo_libre_el_sistema_lo_encuentra(): void
    {
        $this->fillGuestCodesExcept('INV5555');

        $this->postJson('/api/usuarios', $this->guestPayload())
            ->assertCreated()
            ->assertJsonPath('data.cod_sis', 'INV5555');
    }

    public function test_si_se_agotan_los_9000_codigos_informa_un_error_claro(): void
    {
        $this->fillGuestCodesExcept(null);

        $this->postJson('/api/usuarios', $this->guestPayload())
            ->assertStatus(422)
            ->assertJsonPath(
                'message',
                'No quedan códigos disponibles para docentes invitados. '
                . 'Deshabilite cuentas de invitado que ya no se usen o contacte a soporte.'
            );

        $this->assertSame(9000, DB::table('usuario')->where('cod_sis', 'like', 'INV%')->count());
    }

    public function test_el_docente_invitado_inicia_sesion_con_su_codigo_inv_sin_distinguir_mayusculas(): void
    {
        $code = $this->postJson('/api/usuarios', $this->guestPayload())->json('data.cod_sis');

        foreach ([$code, strtolower($code), "  {$code}  "] as $typed) {
            $this->app['auth']->forgetGuards();

            $this->postJson('/api/auth/login', ['cod_sis' => $typed, 'password' => 'Temporal2026'])
                ->assertOk()
                ->assertJsonPath('data.usuario.cod_sis', $code)
                ->assertJsonPath('data.rol.nombre_rol', Role::INVITADO)
                ->assertJsonPath('data.usuario.debe_cambiar_contrasenia', true);
        }
    }

    private function rawUser(string $code, string $email): array
    {
        return [
            'id_usuario' => (string) Str::uuid(),
            'nombre' => 'Vieja', 'apellido_paterno' => 'Cuenta', 'correo' => $email,
            'contrasenia' => 'x', 'cod_sis' => $code, 'estado' => 'ACTIVO',
        ];
    }

    private function fillGuestCodesExcept(?string $free): void
    {
        $rows = [];

        for ($number = 1000; $number <= 9999; $number++) {
            $code = "INV{$number}";

            if ($code === $free) {
                continue;
            }

            $rows[] = $this->rawUser($code, "relleno{$number}@umss.edu");
        }

        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table('usuario')->insert($chunk);
        }
    }
}
