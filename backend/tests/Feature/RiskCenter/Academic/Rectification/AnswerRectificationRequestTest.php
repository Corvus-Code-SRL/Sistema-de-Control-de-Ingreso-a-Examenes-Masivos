<?php

namespace Tests\Feature\RiskCenter\Academic\Rectification;

use App\Models\Role;
use App\Services\EntryControl\EntryControlSnapshotService;
use App\Services\Security\AuditLogService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Concerns\SeedsRectifications;
use Tests\Concerns\SeedsRiskCenterRecords;
use Tests\Concerns\SeedsSecurityAccounts;
use Tests\TestCase;

/**
 * HU-062: el docente que aprobó el reporte acepta o rechaza la solicitud de rectificación.
 * Aceptar retira el registro; rechazar, o dejarla vencer, lo deja en la Central.
 */
class AnswerRectificationRequestTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsRectifications;
    use SeedsRiskCenterRecords;
    use SeedsSecurityAccounts;

    private const IMAGE_MESSAGE = 'Formato o tamaño no permitido. Use una imagen jpg, png o webp de hasta 5 MB.';

    private array $context;

    private int $recordId;

    private string $teacherId;

    private int $requestId;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seedSecurityAccounts();
        $this->context = $this->createReportingContext();
        $this->recordId = $this->insertApprovedRecord($this->context)['id_cr_estudiante'];
        $this->teacherId = $this->makeApproverActive($this->recordId);
        $this->requestId = $this->insertRectificationRequest($this->recordId, [
            'motivo_solicitud' => 'El reporte correspondía a otra persona de la fila.',
        ]);
        $this->actAsUserId($this->teacherId);
    }

    private function accept(?int $id = null)
    {
        return $this->postJson('/api/solicitudes-rectificacion/' . ($id ?? $this->requestId) . '/aceptar');
    }

    private function reject(array $body = [], ?int $id = null)
    {
        $id = $id ?? $this->requestId;

        return $this->postJson('/api/solicitudes-rectificacion/' . $id . '/rechazar', array_merge([
            'motivo' => 'El reporte sí tenía sustento y consta en la evidencia.',
        ], $body));
    }

    private function requestRow(?int $id = null): array
    {
        return (array) DB::table('solicitud_rectificacion')
            ->where('id_solicitud_rect', $id ?? $this->requestId)
            ->first();
    }

    private function auditCount(string $operation): int
    {
        return DB::table('log')->join('accion', 'accion.id_accion', '=', 'log.id_accion')
            ->where('accion.operacion', $operation)->count();
    }

    private function assertStillActive(): void
    {
        $row = $this->recordRow($this->recordId);
        $this->assertNull($row['rectificado_en']);
        $this->assertNull($row['id_usuario_rectificador']);
    }

    public function test_aceptar_retira_el_registro_con_el_administrador_solicitante_y_su_motivo(): void
    {
        $this->accept()
            ->assertOk()
            ->assertJsonPath('data.estado', 'ACEPTADA')
            ->assertJsonPath('data.registro_rectificado', true);

        $row = $this->recordRow($this->recordId);
        $this->assertNotNull($row['rectificado_en']);
        $this->assertSame($this->administratorId, $row['id_usuario_rectificador']);
        $this->assertSame('El reporte correspondía a otra persona de la fila.', $row['motivo_rectificacion']);

        $request = $this->requestRow();
        $this->assertSame('ACEPTADA', $request['estado']);
        $this->assertNotNull($request['fecha_respuesta']);
    }

    public function test_aceptar_escribe_aceptar_rectificacion_y_rectificar_registro_firmadas_por_el_docente(): void
    {
        $this->accept()->assertOk();

        $this->assertSame(1, $this->auditCount('ACEPTAR_RECTIFICACION'));
        $this->assertSame(1, $this->auditCount('RECTIFICAR_REGISTRO'));

        $log = DB::table('log')->join('accion', 'accion.id_accion', '=', 'log.id_accion')
            ->where('accion.operacion', 'RECTIFICAR_REGISTRO')->first();
        $after = json_decode($log->nuevo_valor, true);

        $this->assertSame($this->teacherId, $log->id_usuario);
        $this->assertSame('central_riesgo', $log->tabla_afectada);
        $this->assertSame($this->administratorId, $after['id_usuario_rectificador']);
        $this->assertSame($this->teacherId, $after['id_usuario_confirmador']);
    }

    public function test_aceptar_actualiza_los_antecedentes_del_estudiante_despues_de_confirmar(): void
    {
        $this->mock(EntryControlSnapshotService::class, function ($mock) {
            $mock->shouldReceive('refreshBackground')->once()->with($this->context['id_estudiante']);
        });

        $this->accept()->assertOk();
    }

    public function test_aceptar_no_modifica_el_reporte_original(): void
    {
        $before = (array) DB::table('reporte_estudiante')->where('estado', 'APROBADO')->first();

        $this->accept()->assertOk();

        $this->assertEquals($before, (array) DB::table('reporte_estudiante')->where('estado', 'APROBADO')->first());
    }

    public function test_solo_el_docente_aprobador_puede_responder_y_los_demas_reciben_403(): void
    {
        $other = $this->createAccount(Role::DOCENTE);
        $this->actAs($other);

        $this->accept()
            ->assertForbidden()
            ->assertJsonPath(
                'message',
                'Solo el docente que aprobó este registro puede responder la solicitud de rectificación.'
            );
        $this->reject()->assertForbidden();

        $this->assertSame('PENDIENTE', $this->requestRow()['estado']);
        $this->assertStillActive();
    }

    /** @return array<string, array{0: string}> */
    public function deniedRoles(): array
    {
        return ['administrador' => [Role::ADMINISTRADOR], 'auxiliar' => [Role::AUXILIAR]];
    }

    /** @dataProvider deniedRoles */
    public function test_sin_la_capacidad_de_responder_se_recibe_403(string $role): void
    {
        $this->actAs($this->createAccount($role));

        $this->accept()->assertForbidden();
        $this->reject()->assertForbidden();
        $this->getJson('/api/solicitudes-rectificacion')->assertForbidden();
        $this->getJson('/api/solicitudes-rectificacion/conteo')->assertForbidden();
    }

    public function test_un_invitado_que_aprobo_el_reporte_puede_responder(): void
    {
        $guestId = $this->makeApproverActive($this->recordId, Role::INVITADO);
        DB::table('solicitud_rectificacion')->where('id_solicitud_rect', $this->requestId)
            ->update(['id_usuario_docente' => $guestId]);
        $this->actAsUserId($guestId);

        $this->accept()->assertOk();
    }

    public function test_sin_token_responde_401(): void
    {
        $this->actAsGuest();

        $this->accept()->assertUnauthorized();
    }

    public function test_una_solicitud_inexistente_responde_404(): void
    {
        $this->accept(999999999)->assertNotFound();
        $this->reject([], 999999999)->assertNotFound();
    }

    public function test_responder_una_solicitud_vencida_responde_409_la_marca_vencida_y_no_deja_bitacora(): void
    {
        DB::table('solicitud_rectificacion')->where('id_solicitud_rect', $this->requestId)->update([
            'fecha_solicitud' => now()->subDays(9),
            'vence_en' => now()->subDay(),
        ]);

        $this->accept()
            ->assertStatus(409)
            ->assertJsonPath(
                'message',
                'La solicitud de rectificación venció y ya no puede responderse. El registro sigue en la Central.'
            );
        $this->reject()->assertStatus(409);

        $this->assertSame('VENCIDA', $this->requestRow()['estado']);
        $this->assertStillActive();
        foreach (['ACEPTAR_RECTIFICACION', 'RECHAZAR_RECTIFICACION', 'RECTIFICAR_REGISTRO'] as $operation) {
            $this->assertSame(0, $this->auditCount($operation), $operation);
        }
    }

    public function test_una_solicitud_ya_respondida_no_se_puede_responder_otra_vez(): void
    {
        $this->reject()->assertOk();

        $this->accept()->assertStatus(409)
            ->assertJsonPath('message', 'Esta solicitud de rectificación ya fue respondida.');
        $this->reject()->assertStatus(409);

        $this->assertSame('RECHAZADA', $this->requestRow()['estado']);
        $this->assertStillActive();
    }

    public function test_si_el_registro_ya_fue_rectificado_no_se_puede_responder(): void
    {
        $this->voidRecord($this->recordId);

        $this->accept()
            ->assertStatus(409)
            ->assertJsonPath('message', 'Este registro ya fue rectificado y ya no está en la Central de Riesgo.');

        $row = $this->requestRow();
        $this->assertSame('VENCIDA', $row['estado'], 'La solicitud no puede quedar PENDIENTE de un registro anulado.');
        $this->assertNull($row['fecha_respuesta']);
        $this->assertNull($row['motivo_rechazo']);
        $this->assertSame(0, $this->auditCount('ACEPTAR_RECTIFICACION'));
        $this->reject()->assertStatus(409);
        $this->getJson('/api/solicitudes-rectificacion/conteo')->assertJsonPath('data.total_pendientes', 0);
    }

    public function test_rechazar_sobre_un_registro_ya_rectificado_responde_409_y_cierra_la_solicitud(): void
    {
        $this->voidRecord($this->recordId);

        $this->reject()
            ->assertStatus(409)
            ->assertJsonPath('message', 'Este registro ya fue rectificado y ya no está en la Central de Riesgo.');

        $this->assertSame('VENCIDA', $this->requestRow()['estado']);
        $this->assertSame(0, $this->auditCount('RECHAZAR_RECTIFICACION'));
    }

    public function test_tras_anular_directo_la_solicitud_queda_vencida_el_docente_ya_no_puede_responderla(): void
    {
        $this->assertSame(1, $this->getJson('/api/solicitudes-rectificacion/conteo')->json('data.total_pendientes'));

        $this->makeApproverInactive($this->recordId, 400);
        $this->actAsUserId($this->administratorId);
        $this->postJson($this->rectifyUrl($this->recordId), [
            'motivo' => 'El docente ya no está; el reporte no tenía sustento.',
        ])->assertOk();

        $row = $this->requestRow();
        $this->assertSame('VENCIDA', $row['estado']);
        $this->assertNull($row['fecha_respuesta']);
        $this->assertNull($row['motivo_rechazo']);
        $this->assertNull($row['evidencia_public_id']);

        // El docente vuelve a tener rol: aun así la solicitud ya no se puede responder y no cuenta como pendiente.
        $this->makeApproverActive($this->recordId);
        $this->actAsUserId($this->teacherId);
        $this->accept()->assertStatus(409);
        $this->reject()->assertStatus(409);
        $this->getJson('/api/solicitudes-rectificacion/conteo')->assertJsonPath('data.total_pendientes', 0);
        $this->assertSame('VENCIDA', $this->requestRow()['estado']);
    }

    public function test_rechazar_sin_imagen_deja_el_registro_en_la_central(): void
    {
        $this->reject(['motivo' => '  Hay evidencia suficiente del hecho.  '])
            ->assertOk()
            ->assertJsonPath('data.estado', 'RECHAZADA')
            ->assertJsonPath('data.respuesta.motivo', 'Hay evidencia suficiente del hecho.')
            ->assertJsonPath('data.respuesta.evidencia', null);

        $row = $this->requestRow();
        $this->assertSame('RECHAZADA', $row['estado']);
        $this->assertSame('Hay evidencia suficiente del hecho.', $row['motivo_rechazo']);
        $this->assertNotNull($row['fecha_respuesta']);
        $this->assertNull($row['evidencia_public_id']);
        $this->assertStillActive();
        $this->assertSame(1, $this->auditCount('RECHAZAR_RECTIFICACION'));
        $this->assertSame(0, $this->auditCount('RECTIFICAR_REGISTRO'));
    }

    public function test_rechazar_no_actualiza_los_antecedentes(): void
    {
        $this->mock(EntryControlSnapshotService::class, function ($mock) {
            $mock->shouldReceive('refreshBackground')->never();
        });

        $this->reject()->assertOk();
    }

    /** @return array<string, array{0: string, 1: string}> */
    public function validImages(): array
    {
        return ['jpg' => ['foto.jpg', 'image/jpeg'], 'png' => ['foto.png', 'image/png'],
            'webp' => ['foto.webp', 'image/webp']];
    }

    /** @dataProvider validImages */
    public function test_rechazar_con_imagen_la_guarda_bajo_evidencias_rectificaciones(string $name): void
    {
        $this->reject(['evidencia' => UploadedFile::fake()->create($name, 200, $this->mimeOf($name))])->assertOk();

        $path = $this->requestRow()['evidencia_public_id'];

        $this->assertNotNull($path);
        $this->assertStringStartsWith('evidencias/rectificaciones/', $path);
        Storage::disk('local')->assertExists($path);
    }

    private function mimeOf(string $name): string
    {
        $types = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];

        return $types[pathinfo($name, PATHINFO_EXTENSION)];
    }

    /** @return array<string, array{0: callable}> */
    public function invalidImages(): array
    {
        return [
            'pdf' => [fn () => UploadedFile::fake()->create('acta.pdf', 100, 'application/pdf')],
            'gif' => [fn () => UploadedFile::fake()->create('animada.gif', 100, 'image/gif')],
            'texto renombrado' => [function () {
                $path = tempnam(sys_get_temp_dir(), 'falsa');
                file_put_contents($path, 'esto no es una imagen');

                return new UploadedFile($path, 'falsa.jpg', 'image/jpeg', null, true);
            }],
            'más de 5 MB' => [fn () => UploadedFile::fake()->create('grande.jpg', 5121, 'image/jpeg')],
        ];
    }

    /** @dataProvider invalidImages */
    public function test_una_imagen_no_permitida_responde_422_con_el_mensaje_y_no_guarda_nada(callable $make): void
    {
        $this->reject(['evidencia' => $make()])
            ->assertStatus(422)
            ->assertJsonPath('errors.evidencia.0', self::IMAGE_MESSAGE);

        $this->assertSame('PENDIENTE', $this->requestRow()['estado']);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_una_imagen_de_exactamente_5_mb_es_valida(): void
    {
        $this->reject(['evidencia' => UploadedFile::fake()->create('limite.png', 5120, 'image/png')])->assertOk();
    }

    /** @return array<string, array{0: array<string, mixed>}> */
    public function invalidReasons(): array
    {
        return [
            'sin motivo' => [['motivo' => null]],
            'vacío' => [['motivo' => '']],
            'solo espacios' => [['motivo' => '           ']],
            '9 caracteres' => [['motivo' => str_repeat('a', 9)]],
            '501 caracteres' => [['motivo' => str_repeat('a', 501)]],
        ];
    }

    /** @dataProvider invalidReasons */
    public function test_un_motivo_invalido_responde_422_y_no_cambia_nada(array $body): void
    {
        $this->reject($body)->assertStatus(422)->assertJsonValidationErrors(['motivo']);

        $this->assertSame('PENDIENTE', $this->requestRow()['estado']);
    }

    public function test_sin_motivo_el_mensaje_es_el_motivo_es_obligatorio(): void
    {
        $this->postJson('/api/solicitudes-rectificacion/' . $this->requestId . '/rechazar', [])
            ->assertStatus(422)
            ->assertJsonPath('errors.motivo.0', 'El motivo es obligatorio');
    }

    public function test_un_motivo_de_10_y_de_500_caracteres_es_valido(): void
    {
        $other = $this->insertApprovedRecord($this->context)['id_cr_estudiante'];
        $this->makeApproverActive($other);
        $otherRequest = $this->insertRectificationRequest($other);

        $this->reject(['motivo' => str_repeat('a', 10)])->assertOk();
        $this->reject(['motivo' => str_repeat('b', 500)], $otherRequest)->assertOk();
    }

    public function test_si_falla_la_bitacora_al_rechazar_no_queda_archivo_ni_cambio(): void
    {
        $this->mock(AuditLogService::class, function ($mock) {
            $mock->shouldReceive('registrar')->andThrow(new RuntimeException('Falla la bitácora'));
        });
        $this->withoutExceptionHandling();

        try {
            $this->reject(['evidencia' => UploadedFile::fake()->create('foto.png', 100, 'image/png')]);
            $this->fail('Debió fallar la bitácora.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Falla la bitácora', $exception->getMessage());
        }

        $this->assertSame('PENDIENTE', $this->requestRow()['estado']);
        $this->assertNull($this->requestRow()['evidencia_public_id']);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_si_falla_la_bitacora_al_aceptar_el_registro_no_se_retira(): void
    {
        $this->mock(AuditLogService::class, function ($mock) {
            $mock->shouldReceive('registrar')->andThrow(new RuntimeException('Falla la bitácora'));
        });
        $this->withoutExceptionHandling();

        try {
            $this->accept();
            $this->fail('Debió fallar la bitácora.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Falla la bitácora', $exception->getMessage());
        }

        $this->assertSame('PENDIENTE', $this->requestRow()['estado']);
        $this->assertStillActive();
    }

    public function test_responder_bloquea_la_solicitud_y_despues_el_registro(): void
    {
        $statements = [];
        DB::listen(function ($query) use (&$statements) {
            $statements[] = strtolower($query->sql);
        });

        $this->accept()->assertOk();

        $request = null;
        $record = null;
        foreach ($statements as $index => $sql) {
            if (strpos($sql, 'for update') === false) {
                continue;
            }
            if ($request === null && strpos($sql, 'from "solicitud_rectificacion"') !== false) {
                $request = $index;
            }
            if ($record === null && strpos($sql, 'from "central_riesgo"') !== false) {
                $record = $index;
            }
        }

        $this->assertNotNull($request, 'Debe bloquear la solicitud con FOR UPDATE.');
        $this->assertNotNull($record, 'Debe bloquear el registro con FOR UPDATE.');
        $this->assertLessThan($record, $request, 'Primero la solicitud y después el registro.');
    }

    public function test_el_docente_ve_su_bandeja_con_las_pendientes_primero_y_luego_lo_respondido(): void
    {
        $second = $this->insertApprovedRecord($this->context)['id_cr_estudiante'];
        $this->makeApproverActive($second);
        $soon = $this->insertRectificationRequest($second, ['vence_en' => now()->addDay()]);
        $rejected = $this->insertRectificationRequest($this->insertApprovedRecord($this->context)['id_cr_estudiante'], [
            'estado' => 'RECHAZADA',
            'fecha_respuesta' => now()->subHour(),
            'motivo_rechazo' => 'El reporte sí tenía sustento.',
        ]);
        $expired = $this->insertRectificationRequest($this->insertApprovedRecord($this->context)['id_cr_estudiante'], [
            'fecha_solicitud' => now()->subDays(9),
            'vence_en' => now()->subDays(2),
        ]);

        $response = $this->getJson('/api/solicitudes-rectificacion')->assertOk();

        $this->assertSame(
            [$soon, $this->requestId, $rejected, $expired],
            array_column($response->json('data'), 'id_solicitud_rect')
        );
        $this->assertSame(
            ['PENDIENTE', 'PENDIENTE', 'RECHAZADA', 'VENCIDA'],
            array_column($response->json('data'), 'estado')
        );
        $response->assertJsonPath('meta.total_pendientes', 2);
    }

    public function test_la_bandeja_solo_trae_las_solicitudes_del_docente(): void
    {
        $other = $this->createAccount(Role::DOCENTE);
        $this->actAs($other);

        $this->getJson('/api/solicitudes-rectificacion')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total_pendientes', 0);
    }

    public function test_la_tarjeta_trae_los_datos_del_registro_el_solicitante_y_los_rechazos_previos(): void
    {
        $this->insertRectificationRequest($this->recordId, [
            'estado' => 'RECHAZADA',
            'fecha_solicitud' => now()->subDays(20),
            'fecha_respuesta' => now()->subDays(19),
            'vence_en' => now()->subDays(13),
            'motivo_rechazo' => 'Primer rechazo.',
        ]);
        $this->insertRectificationRequest($this->recordId, [
            'estado' => 'RECHAZADA',
            'fecha_solicitud' => now()->subDays(12),
            'fecha_respuesta' => now()->subDays(11),
            'vence_en' => now()->subDays(5),
            'motivo_rechazo' => 'Segundo rechazo.',
        ]);

        $card = collect($this->getJson('/api/solicitudes-rectificacion')->assertOk()->json('data'))
            ->firstWhere('id_solicitud_rect', $this->requestId);

        $this->assertSame($this->requestId, $card['id_solicitud_rect']);
        $this->assertSame(2, $card['rechazos_previos']);
        $this->assertSame('El reporte correspondía a otra persona de la fila.', $card['motivo_solicitud']);
        $this->assertSame($this->recordId, $card['id_cr_estudiante']);
        foreach (['administrador', 'estudiante', 'examen', 'hecho', 'tipo_falta', 'descripcion', 'fecha_registro',
            'fecha_solicitud', 'vence_en'] as $key) {
            $this->assertArrayHasKey($key, $card);
        }
        $this->assertArrayHasKey('cod_sis', $card['estudiante']);
        $this->assertNull($card['evidencia']);
    }

    public function test_el_conteo_no_incluye_las_vencidas_ni_las_respondidas(): void
    {
        $this->insertRectificationRequest($this->insertApprovedRecord($this->context)['id_cr_estudiante'], [
            'fecha_solicitud' => now()->subDays(9),
            'vence_en' => now()->subDays(2),
        ]);
        $this->insertRectificationRequest($this->insertApprovedRecord($this->context)['id_cr_estudiante'], [
            'estado' => 'ACEPTADA',
            'fecha_respuesta' => now(),
        ]);

        $this->getJson('/api/solicitudes-rectificacion/conteo')
            ->assertOk()
            ->assertJsonPath('data.total_pendientes', 1);
    }

    public function test_el_docente_descarga_la_evidencia_del_reporte_original(): void
    {
        Storage::disk('local')->put('evidencias/reportes/prueba.png', 'contenido-png');
        $withEvidence = $this->insertApprovedRecord($this->context, [
            'evidencia_url' => 'https://ejemplo.test/prueba.png',
            'evidencia_public_id' => 'evidencias/reportes/prueba.png',
        ])['id_cr_estudiante'];
        $this->makeApproverActive($withEvidence);
        $id = $this->insertRectificationRequest($withEvidence);

        $this->get("/api/solicitudes-rectificacion/{$id}/evidencia-reporte")->assertOk();

        $card = collect($this->getJson('/api/solicitudes-rectificacion')->assertOk()->json('data'))
            ->firstWhere('id_solicitud_rect', $id);

        $this->assertSame('prueba.png', $card['evidencia']['nombre_archivo']);
        $this->assertTrue($card['evidencia']['disponible']);
    }

    public function test_otro_docente_no_descarga_la_evidencia_y_sin_evidencia_responde_404(): void
    {
        $this->getJson("/api/solicitudes-rectificacion/{$this->requestId}/evidencia-reporte")->assertNotFound();
        $this->getJson("/api/solicitudes-rectificacion/{$this->requestId}/evidencia-rechazo")->assertNotFound();

        $this->actAs($this->createAccount(Role::DOCENTE));
        $this->getJson("/api/solicitudes-rectificacion/{$this->requestId}/evidencia-reporte")->assertForbidden();
    }

    public function test_la_imagen_del_rechazo_se_descarga_por_el_docente_y_por_el_administrador(): void
    {
        $this->reject(['evidencia' => UploadedFile::fake()->image('prueba.png')])->assertOk();

        $this->get("/api/solicitudes-rectificacion/{$this->requestId}/evidencia-rechazo")->assertOk();

        $this->actAsUserId($this->administratorId);
        $this->get("/api/rectificacion-riesgo/solicitudes/{$this->requestId}/evidencia")->assertOk();

        $this->actAs($this->createAccount(Role::DOCENTE));
        $this->getJson("/api/rectificacion-riesgo/solicitudes/{$this->requestId}/evidencia")->assertForbidden();
    }
}
