<?php

namespace Tests\Feature\RiskCenter\Academic\Rectification;

use App\Models\Role;
use App\Models\User;
use App\Services\EntryControl\EntryControlSnapshotService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\Concerns\SeedsRectifications;
use Tests\Concerns\SeedsRiskCenterRecords;
use Tests\Concerns\SeedsSecurityAccounts;
use Tests\TestCase;

/**
 * HU-062: POST /api/rectificacion-riesgo/registros/{id}/rectificar-sin-confirmacion retira el registro de la Central
 * de Riesgo cuando el docente que lo aprobó ya no está activo y pasó el plazo (`rectify()` deja ese escenario
 * listo). Es irreversible y no edita el tipo de falta ni la descripción. La vía normal, con confirmación del
 * docente, la cubren SendRectificationRequestTest y AnswerRectificationRequestTest.
 */
class RectifyRiskRecordTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsRectifications;
    use SeedsRiskCenterRecords;
    use SeedsSecurityAccounts;

    private array $context;

    private int $recordId;

    private int $reportId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedSecurityAccounts();
        $this->context = $this->createReportingContext();
        $record = $this->insertApprovedRecord($this->context, ['descripcion' => 'Descripción original.']);
        $this->recordId = $record['id_cr_estudiante'];
        $this->reportId = $record['id_reporte_est'];
    }

    private function reportRow(): array
    {
        return (array) DB::table('reporte_estudiante')->where('id_reporte_est', $this->reportId)->first();
    }

    private function assertStillActive(int $recordId): void
    {
        $row = $this->recordRow($recordId);
        $this->assertNull($row['rectificado_en']);
        $this->assertNull($row['id_usuario_rectificador']);
        $this->assertNull($row['motivo_rectificacion']);
    }

    public function test_rectifica_el_registro_con_el_administrador_la_fecha_y_el_motivo(): void
    {
        $this->rectify($this->recordId, ['motivo' => '  Se retira: el estudiante no era el implicado.  '])
            ->assertOk()
            ->assertJsonPath('data.id_cr_estudiante', $this->recordId)
            ->assertJsonPath('data.rectificacion.motivo', 'Se retira: el estudiante no era el implicado.')
            ->assertJsonStructure(['data' => [
                'rectificacion' => ['fecha', 'administrador' => ['nombre_completo'], 'motivo'],
            ]]);

        $row = $this->recordRow($this->recordId);
        $this->assertNotNull($row['rectificado_en']);
        $this->assertSame($this->administratorId, $row['id_usuario_rectificador']);
        $this->assertSame('Se retira: el estudiante no era el implicado.', $row['motivo_rectificacion']);
    }

    public function test_responde_200_y_no_201(): void
    {
        $this->rectify($this->recordId)->assertStatus(200);
    }

    public function test_la_fila_y_el_reporte_originales_no_se_modifican(): void
    {
        $recordBefore = $this->recordRow($this->recordId);
        $reportBefore = $this->reportRow();

        $this->rectify($this->recordId)->assertOk();

        $recordAfter = $this->recordRow($this->recordId);
        foreach (['id_cr_estudiante', 'id_reporte_est', 'id_estudiante', 'fecha_registro'] as $column) {
            $this->assertSame($recordBefore[$column], $recordAfter[$column], $column);
        }
        $this->assertSame($reportBefore, $this->reportRow());
        $this->assertSame(1, DB::table('central_riesgo')->where('id_cr_estudiante', $this->recordId)->count());
    }

    public function test_rectificar_dos_veces_responde_409_y_conserva_la_primera_rectificacion(): void
    {
        $this->rectify($this->recordId, ['motivo' => 'Primer motivo válido.'])->assertOk();
        $first = $this->recordRow($this->recordId);

        $this->rectify($this->recordId, ['motivo' => 'Segundo motivo válido.'])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Este registro ya fue rectificado y ya no está en la Central de Riesgo.');

        $this->assertSame($first, $this->recordRow($this->recordId));
        $this->assertSame(1, DB::table('log')
            ->join('accion', 'accion.id_accion', '=', 'log.id_accion')
            ->where('accion.operacion', 'RECTIFICAR_SIN_CONFIRMACION')->count());
    }

    public function test_otro_administrador_tampoco_puede_rectificar_un_registro_ya_rectificado(): void
    {
        $this->voidRecord($this->recordId, null, 'Motivo previo suficiente.');
        $before = $this->recordRow($this->recordId);
        $this->actAs($this->createAccount(Role::ADMINISTRADOR));

        $this->rectify($this->recordId)->assertStatus(409);

        $this->assertSame($before, $this->recordRow($this->recordId));
    }

    public function test_el_409_no_actualiza_los_antecedentes(): void
    {
        $this->voidRecord($this->recordId);
        $this->mock(EntryControlSnapshotService::class, function ($mock) {
            $mock->shouldReceive('refreshBackground')->never();
        });

        $this->rectify($this->recordId)->assertStatus(409);
    }

    public function test_los_campos_de_la_falta_no_se_pueden_enviar_para_editarla(): void
    {
        $reportBefore = $this->reportRow();
        $otherType = $this->insertFaultType('Otra falta ' . uniqid());

        $this->rectify($this->recordId, [
            'id_tipo_falta' => $otherType,
            'descripcion' => 'Descripción nueva.',
            'id_estudiante' => 1,
            'version_base' => 1,
        ])->assertOk();

        $this->assertSame($reportBefore, $this->reportRow());
        $this->assertSame($this->context['id_estudiante'], (int) $this->recordRow($this->recordId)['id_estudiante']);
    }

    /** @return array<string, array{0: array<string, mixed>}> */
    public function invalidBodies(): array
    {
        return [
            'sin motivo' => [[ 'motivo' => null ]],
            'motivo vacío' => [['motivo' => '']],
            'motivo solo espacios' => [['motivo' => '          ']],
            'motivo de 9 caracteres' => [['motivo' => str_repeat('a', 9)]],
            'motivo de 501 caracteres' => [['motivo' => str_repeat('a', 501)]],
            'motivo que no es texto' => [['motivo' => ['a', 'b']]],
        ];
    }

    /** @dataProvider invalidBodies */
    public function test_un_motivo_invalido_responde_422_y_no_guarda_nada(array $body): void
    {
        $this->postJson($this->rectifyUrl($this->recordId), $body)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['motivo']);

        $this->assertStillActive($this->recordId);
    }

    public function test_sin_cuerpo_el_motivo_es_obligatorio(): void
    {
        $this->postJson($this->rectifyUrl($this->recordId), [])
            ->assertStatus(422)
            ->assertJsonPath('errors.motivo.0', 'El motivo es obligatorio');
    }

    public function test_un_motivo_de_diez_y_de_quinientos_caracteres_es_valido(): void
    {
        $other = $this->insertApprovedRecord($this->context)['id_cr_estudiante'];

        $this->rectify($this->recordId, ['motivo' => str_repeat('a', 10)])->assertOk();
        $this->rectify($other, ['motivo' => str_repeat('b', 500)])->assertOk();
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

        $this->assertStillActive($this->recordId);
    }

    public function test_una_cuenta_inactiva_recibe_403(): void
    {
        $this->actAs($this->createAccount(Role::ADMINISTRADOR, User::ESTADO_INACTIVO));

        $this->rectify($this->recordId)->assertForbidden();
        $this->assertStillActive($this->recordId);
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

    public function test_el_403_y_el_404_no_actualizan_los_antecedentes(): void
    {
        $this->mock(EntryControlSnapshotService::class, function ($mock) {
            $mock->shouldReceive('refreshBackground')->never();
        });

        $this->rectify(999999999)->assertNotFound();
        $this->rectify($this->recordId, ['motivo' => ''])->assertStatus(422);
        $this->actAs($this->createAccount(Role::DOCENTE));
        $this->rectify($this->recordId)->assertForbidden();
    }

    public function test_deja_una_fila_de_bitacora_con_administrador_registro_estudiante_examen_y_motivo(): void
    {
        $this->rectify($this->recordId, ['motivo' => 'Se retira por error de identificación.'])->assertOk();

        $log = DB::table('log')
            ->join('accion', 'accion.id_accion', '=', 'log.id_accion')
            ->where('accion.operacion', 'RECTIFICAR_SIN_CONFIRMACION')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('central_riesgo', $log->tabla_afectada);
        $this->assertSame($this->administratorId, $log->id_usuario);
        $this->assertNotNull($log->fecha_hora);

        $before = json_decode($log->antiguo_valor, true);
        $after = json_decode($log->nuevo_valor, true);
        $identity = [
            'id_cr_estudiante' => $this->recordId,
            'id_reporte_est' => $this->reportId,
            'id_estudiante' => $this->context['id_estudiante'],
            'id_examen' => $this->context['id_examen'],
        ];

        $this->assertEquals($identity + ['estado' => 'ACTIVO'], $before);
        $this->assertSame('RECTIFICADO', $after['estado']);
        $this->assertEquals($identity, array_intersect_key($after, $identity));
        $this->assertSame('Se retira por error de identificación.', $after['motivo_rectificacion']);
        $this->assertSame($this->administratorId, $after['id_usuario_rectificador']);
        $stored = \Carbon\Carbon::parse($this->recordRow($this->recordId)['rectificado_en']);
        $this->assertLessThan(1, abs($stored->floatDiffInSeconds(\Carbon\Carbon::parse($after['rectificado_en']))));
    }

    public function test_la_rectificacion_aparece_en_la_consulta_de_la_bitacora(): void
    {
        $this->rectify($this->recordId)->assertOk();

        $this->getJson('/api/bitacora?accion=RECTIFICAR_SIN_CONFIRMACION&modulo=central_riesgo')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_la_consulta_toma_el_bloqueo_de_la_fila_del_registro(): void
    {
        $statements = [];
        DB::listen(function ($query) use (&$statements) {
            $statements[] = strtolower($query->sql);
        });

        $this->rectify($this->recordId)->assertOk();

        $locks = array_filter($statements, fn (string $sql) => strpos($sql, 'from "central_riesgo"') !== false
            && strpos($sql, 'for update') !== false);
        $this->assertNotEmpty($locks, 'La rectificación debe bloquear la fila de central_riesgo con FOR UPDATE.');
    }

    public function test_la_actualizacion_solo_alcanza_registros_aun_activos(): void
    {
        $statements = [];
        DB::listen(function ($query) use (&$statements) {
            $statements[] = strtolower($query->sql);
        });

        $this->rectify($this->recordId)->assertOk();

        $updates = array_filter($statements, fn (string $sql) => strpos($sql, 'update "central_riesgo"') === 0);
        $this->assertCount(1, $updates);
        $this->assertStringContainsString('"rectificado_en" is null', reset($updates));
    }

    public function test_si_falla_la_bitacora_no_se_rectifica_nada(): void
    {
        $this->mock(\App\Services\Security\AuditLogService::class, function ($mock) {
            $mock->shouldReceive('registrar')->once()->andThrow(new RuntimeException('Falla la bitácora'));
        });

        $this->withoutExceptionHandling();

        try {
            $this->rectify($this->recordId);
            $this->fail('La falla de la bitácora debía propagarse.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Falla la bitácora', $exception->getMessage());
        }

        $this->assertStillActive($this->recordId);
    }

    public function test_un_fallo_de_redis_no_deshace_la_rectificacion(): void
    {
        $this->mock(EntryControlSnapshotService::class, function ($mock) {
            $mock->shouldReceive('refreshBackground')->once()->andThrow(new RuntimeException('Redis caído'));
        });
        Log::spy();

        $this->rectify($this->recordId)->assertOk();

        $this->assertNotNull($this->recordRow($this->recordId)['rectificado_en']);
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_actualiza_los_antecedentes_del_estudiante_tras_confirmar(): void
    {
        $this->mock(EntryControlSnapshotService::class, function ($mock) {
            $mock->shouldReceive('refreshBackground')->once()->with($this->context['id_estudiante']);
        });

        $this->rectify($this->recordId)->assertOk();
    }
}
