<?php

namespace Tests\Feature\RiskCenter\Academic\Rectification;

use App\Models\Role;
use App\Services\EntryControl\EntryControlSnapshotService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsRectifications;
use Tests\Concerns\SeedsRiskCenterRecords;
use Tests\Concerns\SeedsSecurityAccounts;
use Tests\TestCase;

/**
 * HU-062: POST /api/rectificacion-riesgo/registros/{id}/solicitudes envía la solicitud al docente que aprobó el
 * reporte. El registro NO se retira: sigue activo hasta que el docente acepte.
 */
class SendRectificationRequestTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsRectifications;
    use SeedsRiskCenterRecords;
    use SeedsSecurityAccounts;

    private array $context;

    private int $recordId;

    private string $teacherId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedSecurityAccounts();
        $this->context = $this->createReportingContext();
        $this->recordId = $this->insertApprovedRecord($this->context)['id_cr_estudiante'];
        $this->teacherId = $this->makeApproverActive($this->recordId);
    }

    private function requestRow(): ?array
    {
        $row = DB::table('solicitud_rectificacion')->where('id_cr_estudiante', $this->recordId)
            ->orderByDesc('id_solicitud_rect')->first();

        return $row === null ? null : (array) $row;
    }

    private function auditCount(string $operation): int
    {
        return DB::table('log')->join('accion', 'accion.id_accion', '=', 'log.id_accion')
            ->where('accion.operacion', $operation)->count();
    }

    public function test_crea_la_solicitud_pendiente_para_el_docente_aprobador_con_su_vencimiento(): void
    {
        $this->sendRequest($this->recordId, ['motivo' => '  Se solicita retirar: no era el implicado.  '])
            ->assertStatus(201)
            ->assertJsonPath('data.estado', 'PENDIENTE')
            ->assertJsonPath('data.id_cr_estudiante', $this->recordId)
            ->assertJsonPath('data.motivo', 'Se solicita retirar: no era el implicado.')
            ->assertJsonStructure(['data' => ['id_solicitud_rect', 'docente' => ['nombre_completo'],
                'fecha_solicitud', 'vence_en']]);

        $row = $this->requestRow();
        $this->assertSame('PENDIENTE', $row['estado']);
        $this->assertSame($this->administratorId, $row['id_usuario_solicitante']);
        $this->assertSame($this->teacherId, $row['id_usuario_docente']);
        $this->assertNull($row['fecha_respuesta']);

        $days = \Carbon\Carbon::parse($row['fecha_solicitud'])->diffInDays(\Carbon\Carbon::parse($row['vence_en']));
        $this->assertSame(7, $days);
    }

    public function test_el_plazo_de_vencimiento_sale_de_la_configuracion(): void
    {
        config(['sciem.rectificacion.dias_vencimiento' => 3]);

        $this->sendRequest($this->recordId)->assertStatus(201);

        $row = $this->requestRow();
        $this->assertSame(
            3,
            \Carbon\Carbon::parse($row['fecha_solicitud'])->diffInDays(\Carbon\Carbon::parse($row['vence_en']))
        );
    }

    public function test_el_registro_sigue_activo_y_no_se_toca(): void
    {
        $before = $this->recordRow($this->recordId);
        $this->mock(EntryControlSnapshotService::class, function ($mock) {
            $mock->shouldReceive('refreshBackground')->never();
        });

        $this->sendRequest($this->recordId)->assertStatus(201);

        $this->assertSame($before, $this->recordRow($this->recordId));
        $this->assertNull($this->recordRow($this->recordId)['rectificado_en']);
    }

    public function test_registra_solicitar_rectificacion_en_la_bitacora_y_no_rectificar_registro(): void
    {
        $this->sendRequest($this->recordId, ['motivo' => 'Se solicita retirar por error.'])->assertStatus(201);

        $log = DB::table('log')->join('accion', 'accion.id_accion', '=', 'log.id_accion')
            ->where('accion.operacion', 'SOLICITAR_RECTIFICACION')->first();

        $this->assertNotNull($log);
        $this->assertSame('solicitud_rectificacion', $log->tabla_afectada);
        $this->assertSame($this->administratorId, $log->id_usuario);
        $after = json_decode($log->nuevo_valor, true);
        $this->assertSame($this->teacherId, $after['id_usuario_docente']);
        $this->assertSame('Se solicita retirar por error.', $after['motivo_solicitud']);
        $this->assertSame(0, $this->auditCount('RECTIFICAR_REGISTRO'));
    }

    public function test_otra_solicitud_con_una_pendiente_responde_409(): void
    {
        $this->sendRequest($this->recordId)->assertStatus(201);

        $this->sendRequest($this->recordId)
            ->assertStatus(409)
            ->assertJsonPath(
                'message',
                'Este registro ya tiene una solicitud de rectificación pendiente de respuesta del docente.'
            );

        $this->assertSame(1, DB::table('solicitud_rectificacion')->count());
        $this->assertSame(1, $this->auditCount('SOLICITAR_RECTIFICACION'));
    }

    public function test_el_indice_unico_impide_dos_pendientes_aunque_falle_el_bloqueo(): void
    {
        $this->insertRectificationRequest($this->recordId);

        $this->expectException(QueryException::class);

        DB::transaction(fn () => $this->insertRectificationRequest($this->recordId));
    }

    public function test_tras_un_rechazo_se_puede_enviar_una_solicitud_nueva_y_el_historial_se_conserva(): void
    {
        $first = $this->insertRectificationRequest($this->recordId, [
            'estado' => 'RECHAZADA',
            'fecha_respuesta' => now()->subHours(2),
            'motivo_rechazo' => 'El reporte sí tenía sustento.',
        ]);

        $this->sendRequest($this->recordId, ['motivo' => 'Nuevo motivo con nueva información.'])
            ->assertStatus(201);

        $this->assertSame(2, DB::table('solicitud_rectificacion')->count());
        $this->assertSame('RECHAZADA', DB::table('solicitud_rectificacion')->where('id_solicitud_rect', $first)
            ->value('estado'));
    }

    public function test_tras_un_vencimiento_se_puede_enviar_una_solicitud_nueva(): void
    {
        $this->insertRectificationRequest($this->recordId, [
            'estado' => 'VENCIDA',
            'fecha_solicitud' => now()->subDays(8),
            'vence_en' => now()->subDay(),
        ]);

        $this->sendRequest($this->recordId)->assertStatus(201);
    }

    public function test_una_pendiente_pasada_de_fecha_no_bloquea_aunque_la_tarea_no_haya_corrido(): void
    {
        $old = $this->insertRectificationRequest($this->recordId, [
            'fecha_solicitud' => now()->subDays(9),
            'vence_en' => now()->subDays(2),
        ]);

        $this->sendRequest($this->recordId)->assertStatus(201);

        $this->assertSame('VENCIDA', DB::table('solicitud_rectificacion')->where('id_solicitud_rect', $old)
            ->value('estado'));
        $this->assertSame(0, $this->auditCount('RECHAZAR_RECTIFICACION'));
    }

    public function test_un_registro_ya_rectificado_responde_409(): void
    {
        $this->voidRecord($this->recordId);

        $this->sendRequest($this->recordId)
            ->assertStatus(409)
            ->assertJsonPath('message', 'Este registro ya fue rectificado y ya no está en la Central de Riesgo.');
    }

    public function test_un_registro_inexistente_responde_404(): void
    {
        $this->sendRequest(999999999)->assertNotFound();
    }

    public function test_un_motivo_invalido_responde_422_y_no_guarda_nada(): void
    {
        $this->postJson($this->requestUrl($this->recordId), [])
            ->assertStatus(422)
            ->assertJsonPath('errors.motivo.0', 'El motivo es obligatorio');
        $this->sendRequest($this->recordId, ['motivo' => str_repeat('a', 9)])->assertStatus(422);
        $this->sendRequest($this->recordId, ['motivo' => str_repeat('a', 501)])->assertStatus(422);

        $this->assertSame(0, DB::table('solicitud_rectificacion')->count());
    }

    public function test_sin_token_responde_401(): void
    {
        $this->actAsGuest();

        $this->sendRequest($this->recordId)->assertUnauthorized();
    }

    /** @return array<string, array{0: string}> */
    public function deniedRoles(): array
    {
        return ['docente' => [Role::DOCENTE], 'auxiliar' => [Role::AUXILIAR], 'invitado' => [Role::INVITADO]];
    }

    /** @dataProvider deniedRoles */
    public function test_quien_no_es_administrador_recibe_403(string $role): void
    {
        $this->actAs($this->createAccount($role));

        $this->sendRequest($this->recordId)->assertForbidden();

        $this->assertSame(0, DB::table('solicitud_rectificacion')->count());
    }

    public function test_si_el_docente_ya_no_esta_activo_no_se_le_puede_enviar_antes_del_plazo(): void
    {
        $this->makeApproverInactive($this->recordId, 10);
        $expected = now()->subDays(10)->startOfSecond()->addDays(180)
            ->timezone(config('sciem.zona_horaria'))->format('d/m/Y');

        $this->sendRequest($this->recordId)
            ->assertStatus(409)
            ->assertJsonPath(
                'message',
                "El docente que aprobó este registro ya no está activo. Podrá rectificarlo sin su confirmación a "
                . "partir del {$expected}."
            );

        $this->assertSame(0, DB::table('solicitud_rectificacion')->count());
    }

    public function test_si_el_docente_inactivo_ya_cumplio_el_plazo_se_indica_la_via_directa(): void
    {
        $this->makeApproverInactive($this->recordId, 400);

        $this->sendRequest($this->recordId)
            ->assertStatus(409)
            ->assertJsonPath(
                'message',
                'El docente que aprobó este registro ya no está activo, así que no se le puede enviar una '
                . 'solicitud. Rectifique el registro sin su confirmación.'
            );
    }

    public function test_un_docente_con_la_cuenta_deshabilitada_no_recibe_solicitudes(): void
    {
        DB::table('usuario')->where('id_usuario', $this->teacherId)->update(['estado' => 'INACTIVO']);

        $this->sendRequest($this->recordId)->assertStatus(409);
    }

    public function test_un_invitado_que_aprobo_el_reporte_si_recibe_la_solicitud(): void
    {
        $this->makeApproverActive($this->recordId, Role::INVITADO);

        $this->sendRequest($this->recordId)->assertStatus(201);
    }

    public function test_la_consulta_toma_el_bloqueo_del_registro_antes_de_insertar(): void
    {
        $statements = [];
        DB::listen(function ($query) use (&$statements) {
            $statements[] = strtolower($query->sql);
        });

        $this->sendRequest($this->recordId)->assertStatus(201);

        $lock = $this->firstIndex($statements, fn ($sql) => strpos($sql, 'from "central_riesgo"') !== false
            && strpos($sql, 'for update') !== false);
        $insert = $this->firstIndex(
            $statements,
            fn ($sql) => strpos($sql, 'insert into "solicitud_rectificacion"') === 0
        );

        $this->assertNotNull($lock, 'Enviar una solicitud debe bloquear el registro con FOR UPDATE.');
        $this->assertLessThan($insert, $lock);
    }

    /** @param array<int, string> $statements */
    private function firstIndex(array $statements, callable $match): ?int
    {
        foreach ($statements as $index => $sql) {
            if ($match($sql)) {
                return $index;
            }
        }

        return null;
    }
}
