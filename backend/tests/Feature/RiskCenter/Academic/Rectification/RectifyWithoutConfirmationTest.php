<?php

namespace Tests\Feature\RiskCenter\Academic\Rectification;

use App\Models\Role;
use App\Services\EntryControl\EntryControlSnapshotService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsRectifications;
use Tests\Concerns\SeedsRiskCenterRecords;
use Tests\Concerns\SeedsSecurityAccounts;
use Tests\TestCase;

/**
 * HU-062: si el docente que aprobó el reporte ya no está activo, pasados los días configurados el Administrador
 * retira el registro sin su confirmación. Antes del plazo, o con el docente activo, responde 409.
 */
class RectifyWithoutConfirmationTest extends TestCase
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
        $this->recordId = $this->insertApprovedRecord($this->context)['id_cr_estudiante'];
    }

    private function direct(?int $id = null, array $body = [])
    {
        return $this->postJson($this->rectifyUrl($id ?? $this->recordId), array_merge([
            'motivo' => 'El docente ya no está; el reporte no tenía sustento.',
        ], $body));
    }

    private function detectionFor(string $teacherId): ?array
    {
        $row = DB::table('docente_inactivo_deteccion')->where('id_usuario', $teacherId)->first();

        return $row === null ? null : (array) $row;
    }

    private function stateOf(int $requestId): string
    {
        return (string) DB::table('solicitud_rectificacion')->where('id_solicitud_rect', $requestId)->value('estado');
    }

    private function auditCount(string $operation): int
    {
        return DB::table('log')->join('accion', 'accion.id_accion', '=', 'log.id_accion')
            ->where('accion.operacion', $operation)->count();
    }

    private function dateIn(int $daysFromNow): string
    {
        return now()->addDays($daysFromNow)->timezone(config('sciem.zona_horaria'))->format('d/m/Y');
    }

    public function test_con_el_docente_activo_responde_409_y_no_retira_nada(): void
    {
        $this->makeApproverActive($this->recordId);

        $this->direct()
            ->assertStatus(409)
            ->assertJsonPath(
                'message',
                'El docente que aprobó este registro sigue activo: la rectificación necesita su confirmación. '
                . 'Envíele una solicitud.'
            );

        $this->assertNull($this->recordRow($this->recordId)['rectificado_en']);
    }

    public function test_con_el_rol_cerrado_hace_menos_de_los_dias_configurados_responde_409_con_la_fecha(): void
    {
        $this->makeApproverInactive($this->recordId, 179);
        $expected = now()->subDays(179)->startOfSecond()->addDays(180)
            ->timezone(config('sciem.zona_horaria'))->format('d/m/Y');

        $this->direct()
            ->assertStatus(409)
            ->assertJsonPath(
                'message',
                "El docente que aprobó este registro ya no está activo. Podrá rectificarlo sin su confirmación a "
                . "partir del {$expected}."
            );

        $this->assertNull($this->recordRow($this->recordId)['rectificado_en']);
        $this->assertSame(0, $this->auditCount('RECTIFICAR_SIN_CONFIRMACION'));
    }

    public function test_pasados_los_dias_configurados_se_retira_sin_confirmacion_y_queda_en_bitacora(): void
    {
        $teacherId = $this->makeApproverInactive($this->recordId, 181);
        $closedAt = DB::table('usuario_rol')->where('id_usuario', $teacherId)->value('fecha_fin');
        $this->mock(EntryControlSnapshotService::class, function ($mock) {
            $mock->shouldReceive('refreshBackground')->once()->with($this->context['id_estudiante']);
        });

        $this->direct(null, ['motivo' => '  Se retira: el docente ya no está en la universidad.  '])
            ->assertOk()
            ->assertJsonPath('data.rectificacion.motivo', 'Se retira: el docente ya no está en la universidad.');

        $row = $this->recordRow($this->recordId);
        $this->assertNotNull($row['rectificado_en']);
        $this->assertSame($this->administratorId, $row['id_usuario_rectificador']);

        $log = DB::table('log')->join('accion', 'accion.id_accion', '=', 'log.id_accion')
            ->where('accion.operacion', 'RECTIFICAR_SIN_CONFIRMACION')->first();
        $this->assertNotNull($log);
        $this->assertSame('central_riesgo', $log->tabla_afectada);
        $this->assertSame($this->administratorId, $log->id_usuario);

        $after = json_decode($log->nuevo_valor, true);
        $this->assertSame($this->recordId, $after['id_cr_estudiante']);
        $this->assertSame($teacherId, $after['id_usuario_docente_inactivo']);
        $this->assertSame('ROL_CERRADO', $after['fuente_inactividad']);
        $this->assertSame('Se retira: el docente ya no está en la universidad.', $after['motivo_rectificacion']);
        $this->assertSame(
            \Carbon\Carbon::parse($closedAt)->toIso8601String(),
            \Carbon\Carbon::parse($after['inactivo_desde'])->toIso8601String()
        );
        $this->assertSame(0, $this->auditCount('RECTIFICAR_REGISTRO'));
    }

    public function test_cierra_como_vencida_la_solicitud_pendiente_y_sus_ids_quedan_en_la_bitacora(): void
    {
        $this->makeApproverInactive($this->recordId, 181);
        $pending = $this->insertRectificationRequest($this->recordId);
        $rejected = $this->insertRectificationRequest($this->recordId, [
            'estado' => 'RECHAZADA',
            'fecha_solicitud' => now()->subDays(40),
            'vence_en' => now()->subDays(33),
            'fecha_respuesta' => now()->subDays(35),
            'motivo_rechazo' => 'Rechazo anterior con motivo.',
        ]);
        $otherRecord = $this->insertApprovedRecord($this->context)['id_cr_estudiante'];
        $otherPending = $this->insertRectificationRequest($otherRecord);

        $this->direct()->assertOk();

        $closed = (array) DB::table('solicitud_rectificacion')->where('id_solicitud_rect', $pending)->first();
        $this->assertSame('VENCIDA', $closed['estado']);
        $this->assertNull($closed['fecha_respuesta']);
        $this->assertNull($closed['motivo_rechazo']);
        $this->assertNull($closed['evidencia_public_id']);
        $this->assertSame('RECHAZADA', $this->stateOf($rejected));
        $this->assertSame('PENDIENTE', $this->stateOf($otherPending));

        $log = DB::table('log')->join('accion', 'accion.id_accion', '=', 'log.id_accion')
            ->where('accion.operacion', 'RECTIFICAR_SIN_CONFIRMACION')->first();
        $this->assertSame([$pending], json_decode($log->nuevo_valor, true)['solicitudes_cerradas']);
        $this->assertSame(
            0,
            DB::table('log')->where('tabla_afectada', 'solicitud_rectificacion')->count(),
            'Cerrar la solicitud no deja una entrada de bitácora propia.'
        );
    }

    public function test_sin_solicitudes_pendientes_la_bitacora_trae_la_lista_de_cerradas_vacia(): void
    {
        $this->makeApproverInactive($this->recordId, 181);

        $this->direct()->assertOk();

        $log = DB::table('log')->join('accion', 'accion.id_accion', '=', 'log.id_accion')
            ->where('accion.operacion', 'RECTIFICAR_SIN_CONFIRMACION')->first();
        $this->assertSame([], json_decode($log->nuevo_valor, true)['solicitudes_cerradas']);
    }

    public function test_si_la_anulacion_directa_responde_409_la_solicitud_sigue_pendiente(): void
    {
        $this->makeApproverInactive($this->recordId, 100);
        $pending = $this->insertRectificationRequest($this->recordId);

        $this->direct()->assertStatus(409);

        $this->assertSame('PENDIENTE', $this->stateOf($pending));
    }

    public function test_el_plazo_sale_de_la_configuracion(): void
    {
        config(['sciem.rectificacion.dias_docente_inactivo' => 30]);
        $this->makeApproverInactive($this->recordId, 31);

        $this->direct()->assertOk();
    }

    public function test_un_docente_que_pasa_a_otro_rol_cuenta_desde_el_cierre_de_su_rol_docente(): void
    {
        $teacherId = $this->makeApproverInactive($this->recordId, 200);
        DB::table('usuario_rol')->insert([
            'id_usuario' => $teacherId,
            'id_rol' => $this->roleId(Role::AUXILIAR),
            'fecha_inicio' => now()->subDays(200),
        ]);

        $this->direct()->assertOk();

        $after = json_decode(DB::table('log')->join('accion', 'accion.id_accion', '=', 'log.id_accion')
            ->where('accion.operacion', 'RECTIFICAR_SIN_CONFIRMACION')->value('nuevo_valor'), true);
        $this->assertSame('ROL_CERRADO', $after['fuente_inactividad']);
    }

    public function test_cuenta_deshabilitada_con_el_rol_abierto_cuenta_desde_la_primera_deteccion(): void
    {
        $teacherId = $this->makeApproverActive($this->recordId);
        DB::table('usuario')->where('id_usuario', $teacherId)->update(['estado' => 'INACTIVO']);

        $this->direct()
            ->assertStatus(409)
            ->assertJsonPath(
                'message',
                'El docente que aprobó este registro ya no está activo. Podrá rectificarlo sin su confirmación a '
                . 'partir del ' . $this->dateIn(180) . '.'
            );

        $detection = $this->detectionFor($teacherId);
        $this->assertNotNull($detection, 'La primera detección debe quedar guardada aunque la respuesta sea 409.');
        $this->assertNull($this->recordRow($this->recordId)['rectificado_en']);
    }

    public function test_la_primera_deteccion_no_se_reinicia_en_los_intentos_siguientes(): void
    {
        $teacherId = $this->makeApproverActive($this->recordId);
        DB::table('usuario')->where('id_usuario', $teacherId)->update(['estado' => 'INACTIVO']);

        $this->direct()->assertStatus(409);
        $first = $this->detectionFor($teacherId)['detectado_en'];

        $this->direct()->assertStatus(409);
        $this->sendRequest($this->recordId)->assertStatus(409);

        $this->assertSame($first, $this->detectionFor($teacherId)['detectado_en']);
        $this->assertSame(1, DB::table('docente_inactivo_deteccion')->count());
    }

    public function test_cumplido_el_plazo_desde_la_primera_deteccion_se_retira_y_la_bitacora_indica_la_fuente(): void
    {
        $teacherId = $this->makeApproverActive($this->recordId);
        DB::table('usuario')->where('id_usuario', $teacherId)->update(['estado' => 'INACTIVO']);
        $this->direct()->assertStatus(409);

        DB::table('docente_inactivo_deteccion')->where('id_usuario', $teacherId)
            ->update(['detectado_en' => now()->subDays(181)]);

        $this->direct()->assertOk();

        $after = json_decode(DB::table('log')->join('accion', 'accion.id_accion', '=', 'log.id_accion')
            ->where('accion.operacion', 'RECTIFICAR_SIN_CONFIRMACION')->value('nuevo_valor'), true);
        $this->assertSame('PRIMERA_DETECCION', $after['fuente_inactividad']);
        $this->assertSame($teacherId, $after['id_usuario_docente_inactivo']);
    }

    public function test_un_reporte_sin_aprobador_se_trata_como_docente_inactivo_con_primera_deteccion(): void
    {
        // La base ya impide un reporte APROBADO sin revisor; el servicio lo trata igual por defensa. El CHECK se
        // quita solo dentro de la transacción de la prueba, que se revierte al terminar.
        DB::statement('ALTER TABLE reporte_estudiante DROP CONSTRAINT chk_reporte_estudiante_revision');
        $orphan = $this->insertApprovedRecord($this->context, [
            'fecha_revision' => null,
            'id_usuario_revisor' => null,
        ])['id_cr_estudiante'];

        $this->direct($orphan)->assertStatus(409);
        $this->sendRequest($orphan)->assertStatus(409);

        $detection = DB::table('docente_inactivo_deteccion')->where('id_cr_estudiante', $orphan)->first();
        $this->assertNotNull($detection);
        $this->assertNull($detection->id_usuario);

        DB::table('docente_inactivo_deteccion')->where('id_cr_estudiante', $orphan)
            ->update(['detectado_en' => now()->subDays(181)]);

        $this->direct($orphan)->assertOk();

        $after = json_decode(DB::table('log')->join('accion', 'accion.id_accion', '=', 'log.id_accion')
            ->where('accion.operacion', 'RECTIFICAR_SIN_CONFIRMACION')->value('nuevo_valor'), true);
        $this->assertSame('PRIMERA_DETECCION', $after['fuente_inactividad']);
        $this->assertNull($after['id_usuario_docente_inactivo']);
    }

    public function test_un_docente_que_vuelve_a_estar_activo_pierde_su_deteccion_al_intentar_otra_vez(): void
    {
        $teacherId = $this->makeApproverActive($this->recordId);
        DB::table('usuario')->where('id_usuario', $teacherId)->update(['estado' => 'INACTIVO']);
        $this->direct()->assertStatus(409);
        $this->assertNotNull($this->detectionFor($teacherId));

        DB::table('usuario')->where('id_usuario', $teacherId)->update(['estado' => 'ACTIVO']);
        $this->sendRequest($this->recordId)->assertStatus(201);

        $this->assertNull($this->detectionFor($teacherId));
    }

    public function test_un_invitado_inactivo_se_trata_igual_que_un_docente(): void
    {
        $teacherId = $this->makeApproverActive($this->recordId, Role::INVITADO);
        DB::table('usuario_rol')->where('id_usuario', $teacherId)->update(['fecha_fin' => now()->subDays(250)]);

        $this->direct()->assertOk();
    }

    public function test_un_registro_ya_rectificado_responde_409(): void
    {
        $this->makeApproverInactive($this->recordId, 400);
        $this->voidRecord($this->recordId);

        $this->direct()
            ->assertStatus(409)
            ->assertJsonPath('message', 'Este registro ya fue rectificado y ya no está en la Central de Riesgo.');
    }

    public function test_la_ruta_anterior_de_rectificar_ya_no_existe(): void
    {
        $this->postJson("/api/rectificacion-riesgo/registros/{$this->recordId}/rectificar", [
            'motivo' => 'Se retira por un error de identificación.',
        ])->assertNotFound();
    }

    public function test_quien_no_es_administrador_recibe_403(): void
    {
        $this->makeApproverInactive($this->recordId, 400);
        $this->actAs($this->createAccount(Role::DOCENTE));

        $this->direct()->assertForbidden();
        $this->assertNull($this->recordRow($this->recordId)['rectificado_en']);
    }

    public function test_la_ficha_del_administrador_informa_el_estado_del_docente_aprobador(): void
    {
        $this->makeApproverInactive($this->recordId, 10);

        $teacher = $this->getJson("/api/rectificacion-riesgo/registros/{$this->recordId}")
            ->assertOk()
            ->json('data.rectificacion.docente');

        $this->assertFalse($teacher['activo']);
        $this->assertSame('ROL_CERRADO', $teacher['fuente']);
        $this->assertFalse($teacher['puede_rectificar_sin_confirmacion']);
        $this->assertNotNull($teacher['puede_rectificar_desde']);

        $this->makeApproverInactive($this->recordId, 200);

        $this->getJson("/api/rectificacion-riesgo/registros/{$this->recordId}")
            ->assertJsonPath('data.rectificacion.docente.puede_rectificar_sin_confirmacion', true);
    }

    public function test_consultar_la_ficha_no_guarda_la_primera_deteccion(): void
    {
        $teacherId = $this->makeApproverActive($this->recordId);
        DB::table('usuario')->where('id_usuario', $teacherId)->update(['estado' => 'INACTIVO']);

        $this->getJson("/api/rectificacion-riesgo/registros/{$this->recordId}")
            ->assertOk()
            ->assertJsonPath('data.rectificacion.docente.activo', false)
            ->assertJsonPath('data.rectificacion.docente.fuente', null)
            ->assertJsonPath('data.rectificacion.docente.puede_rectificar_desde', null);

        $this->assertNull($this->detectionFor($teacherId));
    }
}
