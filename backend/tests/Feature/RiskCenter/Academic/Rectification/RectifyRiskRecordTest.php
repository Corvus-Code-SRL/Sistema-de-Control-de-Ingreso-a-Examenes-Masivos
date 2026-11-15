<?php

namespace Tests\Feature\RiskCenter\Academic\Rectification;

use App\Models\Role;
use App\Models\User;
use App\Services\EntryControl\EntryControlSnapshotService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\Concerns\SeedsRectifications;
use Tests\Concerns\SeedsRiskCenterRecords;
use Tests\Concerns\SeedsSecurityAccounts;
use Tests\TestCase;

/**
 * HU-062: POST /api/rectificacion-riesgo/registros/{id}/rectificar crea una versión nueva del registro
 * sin sobrescribir el original.
 */
class RectifyRiskRecordTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsRectifications;
    use SeedsRiskCenterRecords;
    use SeedsSecurityAccounts;

    private array $context;

    private int $recordId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedSecurityAccounts();
        $this->context = $this->createReportingContext();
        $this->recordId = $this->insertApprovedRecord($this->context, ['descripcion' => 'Descripción original.'])
            ['id_cr_estudiante'];
    }

    /** @return array<string, mixed> */
    private function rowOf(string $table, string $key, int $id): array
    {
        return (array) DB::table($table)->where($key, $id)->first();
    }

    public function test_la_primera_rectificacion_crea_la_version_uno_y_la_dos_vigente(): void
    {
        $newTypeId = $this->insertFaultType('Suplantación');

        $this->rectify($this->recordId, [
            'id_tipo_falta' => $newTypeId,
            'descripcion' => '  Nueva descripción.  ',
            'motivo' => '  Se confirmó otra falta.  ',
        ])
            ->assertCreated()
            ->assertJsonPath('data.estado', 'Rectificado')
            ->assertJsonPath('data.version_vigente', 2)
            ->assertJsonPath('data.tipo_falta.id_falta', $newTypeId)
            ->assertJsonPath('data.descripcion', 'Nueva descripción.')
            ->assertJsonCount(2, 'data.historial');

        [$first, $second] = $this->versionsOf($this->recordId);

        $this->assertSame(1, $first['numero_version']);
        $this->assertSame($this->context['id_tipo_falta'], $first['id_tipo_falta']);
        $this->assertSame('Descripción original.', $first['descripcion']);
        $this->assertNull($first['motivo']);
        $this->assertSame($this->context['id_usuario'], $first['id_usuario_autor']);
        $this->assertSame('2026-10-02 12:00:00+00', $first['fecha_registro']);
        $this->assertFalse($first['es_vigente']);

        $this->assertSame(2, $second['numero_version']);
        $this->assertSame($newTypeId, $second['id_tipo_falta']);
        $this->assertSame('Nueva descripción.', $second['descripcion']);
        $this->assertSame('Se confirmó otra falta.', $second['motivo']);
        $this->assertSame($this->administratorId, $second['id_usuario_autor']);
        $this->assertTrue($second['es_vigente']);
    }

    public function test_cada_rectificacion_agrega_una_version_consecutiva_y_deja_una_sola_vigente(): void
    {
        $this->rectify($this->recordId)->assertCreated();
        $this->rectify($this->recordId, ['version_base' => 2])->assertCreated();
        $this->rectify($this->recordId, ['version_base' => 3])->assertCreated()
            ->assertJsonPath('data.numero_rectificaciones', 3)
            ->assertJsonPath('data.version_vigente', 4);

        $versions = $this->versionsOf($this->recordId);

        $this->assertSame([1, 2, 3, 4], array_column($versions, 'numero_version'));
        $this->assertSame([false, false, false, true], array_column($versions, 'es_vigente'));
    }

    public function test_el_registro_y_el_reporte_originales_no_se_modifican(): void
    {
        $recordBefore = $this->rowOf('central_riesgo', 'id_cr_estudiante', $this->recordId);
        $reportBefore = $this->rowOf('reporte_estudiante', 'id_reporte_est', $recordBefore['id_reporte_est']);

        $this->rectify($this->recordId)->assertCreated();
        $this->rectify($this->recordId, ['version_base' => 2])->assertCreated();

        $this->assertSame($recordBefore, $this->rowOf('central_riesgo', 'id_cr_estudiante', $this->recordId));
        $this->assertSame(
            $reportBefore,
            $this->rowOf('reporte_estudiante', 'id_reporte_est', $recordBefore['id_reporte_est'])
        );
    }

    public function test_una_version_guardada_solo_cambia_su_bandera_de_vigente(): void
    {
        $this->rectify($this->recordId)->assertCreated();
        $beforeSecond = $this->versionsOf($this->recordId)[1];

        $this->rectify($this->recordId, ['version_base' => 2])->assertCreated();

        $versions = $this->versionsOf($this->recordId);
        $afterSecond = $versions[1];
        $this->assertFalse($afterSecond['es_vigente']);
        $this->assertSame(
            array_diff_key($beforeSecond, ['es_vigente' => 1]),
            array_diff_key($afterSecond, ['es_vigente' => 1])
        );
        $this->assertSame('Descripción original.', $versions[0]['descripcion']);
    }

    public function test_una_descripcion_vacia_se_guarda_como_nula(): void
    {
        $this->rectify($this->recordId, ['descripcion' => '   '])->assertCreated()
            ->assertJsonPath('data.descripcion', null);

        $this->assertNull($this->versionsOf($this->recordId)[1]['descripcion']);
    }

    public function test_se_puede_corregir_solo_la_descripcion(): void
    {
        $this->rectify($this->recordId, [
            'id_tipo_falta' => $this->context['id_tipo_falta'],
            'descripcion' => 'Otra descripción.',
        ])->assertCreated();

        $second = $this->versionsOf($this->recordId)[1];
        $this->assertSame($this->context['id_tipo_falta'], $second['id_tipo_falta']);
        $this->assertSame('Otra descripción.', $second['descripcion']);
    }

    public function test_se_puede_corregir_solo_el_tipo_de_falta(): void
    {
        $this->rectify($this->recordId, ['descripcion' => 'Descripción original.'])->assertCreated();

        $this->assertSame('Descripción original.', $this->versionsOf($this->recordId)[1]['descripcion']);
    }

    public function test_sin_cambios_responde_409_y_no_guarda_nada(): void
    {
        foreach (['Descripción original.', '  Descripción original.  '] as $description) {
            $this->rectify($this->recordId, [
                'id_tipo_falta' => $this->context['id_tipo_falta'],
                'descripcion' => $description,
            ])
                ->assertStatus(409)
                ->assertJsonPath('message', 'Los valores indicados son iguales a los de la versión vigente.');
        }

        $this->assertSame([], $this->versionsOf($this->recordId));
        $this->assertSame(0, DB::table('log')->where('tabla_afectada', 'central_riesgo')->count());
    }

    public function test_sin_cambios_frente_a_la_version_vigente_rectificada_responde_409(): void
    {
        $this->rectify($this->recordId, ['id_tipo_falta' => $this->context['id_tipo_falta'], 'descripcion' => 'Nueva.'])
            ->assertCreated();

        $this->rectify($this->recordId, [
            'id_tipo_falta' => $this->context['id_tipo_falta'],
            'descripcion' => 'Nueva.',
            'version_base' => 2,
        ])->assertStatus(409);

        $this->assertCount(2, $this->versionsOf($this->recordId));
    }

    public function test_una_version_base_desactualizada_responde_409_y_no_guarda_nada(): void
    {
        $this->rectify($this->recordId)->assertCreated();

        $this->rectify($this->recordId, ['version_base' => 1])
            ->assertStatus(409)
            ->assertJsonPath(
                'message',
                'El registro fue rectificado mientras usted lo editaba. Recargue para ver la versión vigente.'
            );

        $versions = $this->versionsOf($this->recordId);
        $this->assertSame([1, 2], array_column($versions, 'numero_version'));
        $this->assertSame([false, true], array_column($versions, 'es_vigente'));
        $this->assertSame(1, DB::table('log')->where('tabla_afectada', 'central_riesgo')->count());
    }

    public function test_una_version_base_adelantada_tambien_responde_409(): void
    {
        $this->rectify($this->recordId, ['version_base' => 5])->assertStatus(409);

        $this->assertSame([], $this->versionsOf($this->recordId));
    }

    /** @return array<string, array{0: array<string, mixed>, 1: string}> */
    public function invalidBodies(): array
    {
        return [
            'sin motivo' => [['motivo' => null], 'motivo'],
            'motivo en blanco' => [['motivo' => '      '], 'motivo'],
            'motivo corto' => [['motivo' => 'corto'], 'motivo'],
            'motivo largo' => [['motivo' => str_repeat('a', 501)], 'motivo'],
            'sin tipo de falta' => [['id_tipo_falta' => null], 'id_tipo_falta'],
            'tipo de falta inexistente' => [['id_tipo_falta' => 999999999], 'id_tipo_falta'],
            'tipo de falta no numérico' => [['id_tipo_falta' => 'abc'], 'id_tipo_falta'],
            'descripción larga' => [['descripcion' => str_repeat('a', 2001)], 'descripcion'],
            'sin versión base' => [['version_base' => null], 'version_base'],
            'versión base cero' => [['version_base' => 0], 'version_base'],
        ];
    }

    /**
     * @dataProvider invalidBodies
     * @param array<string, mixed> $overrides
     */
    public function test_un_cuerpo_invalido_responde_422_y_no_guarda_nada(array $overrides, string $field): void
    {
        $this->rectify($this->recordId, $overrides)
            ->assertStatus(422)
            ->assertJsonValidationErrors($field);

        $this->assertSame([], $this->versionsOf($this->recordId));
        $this->assertSame(0, DB::table('log')->where('tabla_afectada', 'central_riesgo')->count());
    }

    public function test_el_motivo_faltante_dice_que_es_obligatorio(): void
    {
        $this->rectify($this->recordId, ['motivo' => ''])
            ->assertStatus(422)
            ->assertJsonPath('errors.motivo.0', 'El motivo es obligatorio');
    }

    public function test_el_motivo_de_diez_y_de_quinientos_caracteres_es_valido(): void
    {
        $this->rectify($this->recordId, ['motivo' => str_repeat('a', 10)])->assertCreated();
        $this->rectify($this->recordId, ['motivo' => str_repeat('b', 500), 'version_base' => 2])->assertCreated();
    }

    public function test_los_campos_fijos_no_se_pueden_enviar_para_cambiarlos(): void
    {
        $otherStudent = $this->insertStudent('Otro', 'Estudiante', null, '202499992');
        $before = $this->rowOf('central_riesgo', 'id_cr_estudiante', $this->recordId);

        $this->rectify($this->recordId, [
            'id_estudiante' => $otherStudent,
            'id_examen' => 1,
            'fecha_registro' => '2020-01-01',
            'evidencia_url' => 'http://x',
        ])->assertCreated();

        $this->assertSame($before, $this->rowOf('central_riesgo', 'id_cr_estudiante', $this->recordId));
        $this->assertSame(
            $this->context['id_estudiante'],
            (int) DB::table('reporte_estudiante')->where('id_reporte_est', $before['id_reporte_est'])->value('id_estudiante')
        );
    }

    public function test_sin_token_responde_401(): void
    {
        $this->actAsGuest();

        $this->rectify($this->recordId)->assertUnauthorized();
    }

    /** @return array<string, array{0: string}> */
    public function deniedRoles(): array
    {
        return ['docente' => [Role::DOCENTE], 'auxiliar' => [Role::AUXILIAR], 'invitado' => [Role::INVITADO]];
    }

    /** @dataProvider deniedRoles */
    public function test_quien_no_es_administrador_recibe_403_y_no_se_guarda_nada(string $role): void
    {
        $this->actAs($this->createAccount($role));

        $this->rectify($this->recordId)
            ->assertForbidden()
            ->assertJsonPath('message', 'No tiene permiso para rectificar registros de la Central de Riesgo.');

        $this->assertSame([], $this->versionsOf($this->recordId));
    }

    public function test_una_cuenta_inactiva_recibe_403(): void
    {
        $this->actAs($this->createAccount(Role::ADMINISTRADOR, User::ESTADO_INACTIVO));

        $this->rectify($this->recordId)->assertForbidden();
    }

    public function test_un_docente_que_consulta_la_central_no_puede_rectificar(): void
    {
        $this->actAs($this->createAccount(Role::DOCENTE));

        $this->getJson('/api/central-riesgo')->assertOk();
        $this->rectify($this->recordId)->assertForbidden();
    }

    public function test_un_registro_inexistente_responde_404(): void
    {
        $this->rectify(999999999)->assertNotFound();
        $this->rectify(1234567890)->assertNotFound();
    }

    public function test_deja_una_fila_de_bitacora_con_el_administrador_los_valores_y_el_motivo(): void
    {
        $newTypeId = $this->insertFaultType('Suplantación');

        $this->rectify($this->recordId, [
            'id_tipo_falta' => $newTypeId,
            'descripcion' => 'Nueva descripción.',
            'motivo' => 'Se confirmó otra falta.',
        ])->assertCreated();

        $log = DB::table('log')
            ->join('accion', 'accion.id_accion', '=', 'log.id_accion')
            ->where('accion.operacion', 'RECTIFICAR_REGISTRO')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('central_riesgo', $log->tabla_afectada);
        $this->assertSame($this->administratorId, $log->id_usuario);
        $this->assertNotNull($log->fecha_hora);

        $before = json_decode($log->antiguo_valor, true);
        $after = json_decode($log->nuevo_valor, true);
        $this->assertSame($this->recordId, $before['id_cr_estudiante']);
        $this->assertSame(1, $before['numero_version']);
        $this->assertSame($this->context['id_tipo_falta'], $before['id_tipo_falta']);
        $this->assertSame('Descripción original.', $before['descripcion']);
        $this->assertSame($this->recordId, $after['id_cr_estudiante']);
        $this->assertSame(2, $after['numero_version']);
        $this->assertSame($newTypeId, $after['id_tipo_falta']);
        $this->assertSame('Nueva descripción.', $after['descripcion']);
        $this->assertSame('Se confirmó otra falta.', $after['motivo']);
    }

    public function test_la_rectificacion_aparece_en_la_consulta_de_la_bitacora(): void
    {
        $this->rectify($this->recordId)->assertCreated();

        $this->getJson('/api/bitacora?accion=RECTIFICAR_REGISTRO&modulo=central_riesgo')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_la_consulta_toma_el_bloqueo_de_la_fila_del_registro(): void
    {
        $statements = [];
        DB::listen(function ($query) use (&$statements) {
            $statements[] = strtolower($query->sql);
        });

        $this->rectify($this->recordId)->assertCreated();

        $locks = array_filter($statements, fn (string $sql) => strpos($sql, 'from "central_riesgo"') !== false
            && strpos($sql, 'for update') !== false);
        $this->assertNotEmpty($locks, 'La rectificación debe bloquear la fila de central_riesgo con FOR UPDATE.');
    }

    public function test_el_indice_parcial_impide_dos_versiones_vigentes(): void
    {
        $this->rectify($this->recordId)->assertCreated();
        $current = $this->versionsOf($this->recordId)[1];

        $this->expectException(QueryException::class);

        DB::table('central_riesgo_version')->insert([
            'id_cr_estudiante' => $this->recordId,
            'numero_version' => 3,
            'id_tipo_falta' => $current['id_tipo_falta'],
            'motivo' => 'Duplicada vigente',
            'id_usuario_autor' => $this->administratorId,
            'es_vigente' => true,
        ]);
    }

    public function test_no_se_repite_el_numero_de_version_de_un_registro(): void
    {
        $this->rectify($this->recordId)->assertCreated();
        $current = $this->versionsOf($this->recordId)[1];

        $this->expectException(QueryException::class);

        DB::table('central_riesgo_version')->insert([
            'id_cr_estudiante' => $this->recordId,
            'numero_version' => 2,
            'id_tipo_falta' => $current['id_tipo_falta'],
            'motivo' => 'Número repetido',
            'id_usuario_autor' => $this->administratorId,
            'es_vigente' => false,
        ]);
    }

    public function test_una_version_posterior_a_la_primera_exige_motivo(): void
    {
        $this->rectify($this->recordId)->assertCreated();

        $this->expectException(QueryException::class);

        DB::table('central_riesgo_version')->insert([
            'id_cr_estudiante' => $this->recordId,
            'numero_version' => 3,
            'id_tipo_falta' => $this->context['id_tipo_falta'],
            'motivo' => '   ',
            'id_usuario_autor' => $this->administratorId,
            'es_vigente' => false,
        ]);
    }

    public function test_un_fallo_de_redis_no_deshace_la_rectificacion(): void
    {
        $this->mock(EntryControlSnapshotService::class, function ($mock) {
            $mock->shouldReceive('refreshBackground')->once()->andThrow(new RuntimeException('Redis caído'));
        });
        Log::spy();

        $this->rectify($this->recordId)->assertCreated();

        $this->assertCount(2, $this->versionsOf($this->recordId));
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_actualiza_los_antecedentes_del_estudiante_tras_confirmar(): void
    {
        $this->mock(EntryControlSnapshotService::class, function ($mock) {
            $mock->shouldReceive('refreshBackground')->once()->with($this->context['id_estudiante']);
        });

        $this->rectify($this->recordId)->assertCreated();
    }

    public function test_si_la_rectificacion_falla_no_se_actualizan_los_antecedentes(): void
    {
        $this->mock(EntryControlSnapshotService::class, function ($mock) {
            $mock->shouldReceive('refreshBackground')->never();
        });

        $this->rectify($this->recordId, ['motivo' => ''])->assertStatus(422);
        $this->rectify($this->recordId, ['version_base' => 9])->assertStatus(409);
    }
}
