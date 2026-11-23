<?php

namespace Tests\Feature\RiskCenter\Academic\Rectification;

use App\Models\Role;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\Concerns\SeedsRectifications;
use Tests\Concerns\SeedsRiskCenterRecords;
use Tests\Concerns\SeedsSecurityAccounts;
use Tests\TestCase;

/**
 * HU-062: la migración de la doble confirmación crea solicitud_rectificacion y docente_inactivo_deteccion,
 * registra las cuatro acciones de bitácora, se puede repetir y no se deja revertir si perdería historial.
 */
class SolicitudRectificacionMigrationTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsRectifications;
    use SeedsRiskCenterRecords;
    use SeedsSecurityAccounts;

    private const ACTIONS = [
        'SOLICITAR_RECTIFICACION' => 'INSERT',
        'ACEPTAR_RECTIFICACION' => 'UPDATE',
        'RECHAZAR_RECTIFICACION' => 'UPDATE',
        'RECTIFICAR_SIN_CONFIRMACION' => 'UPDATE',
    ];

    private array $context;

    private int $recordId;

    protected function setUp(): void
    {
        parent::setUp();

        require_once database_path('migrations/2026_11_21_000000_create_solicitud_rectificacion.php');

        $this->seedSecurityAccounts();
        $this->context = $this->createReportingContext();
        $this->recordId = $this->insertApprovedRecord($this->context)['id_cr_estudiante'];
        $this->makeApproverActive($this->recordId);
    }

    private function migration(): \CreateSolicitudRectificacion
    {
        return new \CreateSolicitudRectificacion();
    }

    private function assertRejected(callable $insert, string $message): void
    {
        try {
            DB::transaction($insert);
            $this->fail($message);
        } catch (QueryException $exception) {
            $this->assertSame('23514', (string) $exception->getCode(), $message);
        }
    }

    private function row(array $overrides): array
    {
        return array_merge([
            'id_cr_estudiante' => $this->recordId,
            'id_usuario_solicitante' => $this->administratorId,
            'motivo_solicitud' => 'Motivo de la solicitud.',
            'id_usuario_docente' => $this->approverId($this->recordId),
            'estado' => 'PENDIENTE',
            'fecha_solicitud' => now()->subDay(),
            'vence_en' => now()->addDays(6),
        ], $overrides);
    }

    public function test_crea_las_dos_tablas_y_no_toca_central_riesgo(): void
    {
        $this->assertTrue(Schema::hasColumns('solicitud_rectificacion', [
            'id_solicitud_rect', 'id_cr_estudiante', 'id_usuario_solicitante', 'motivo_solicitud',
            'id_usuario_docente', 'estado', 'fecha_solicitud', 'vence_en', 'fecha_respuesta', 'motivo_rechazo',
            'evidencia_public_id',
        ]));
        $this->assertTrue(Schema::hasColumns('docente_inactivo_deteccion', [
            'id_deteccion', 'id_usuario', 'id_cr_estudiante', 'detectado_en',
        ]));
        $this->assertSame(
            ['id_cr_estudiante', 'id_reporte_est', 'id_estudiante', 'fecha_registro', 'rectificado_en',
                'id_usuario_rectificador', 'motivo_rectificacion'],
            Schema::getColumnListing('central_riesgo')
        );
    }

    public function test_registra_las_cuatro_acciones_una_sola_vez_aunque_se_repita(): void
    {
        $this->migration()->up();
        $this->migration()->up();

        foreach (self::ACTIONS as $operation => $type) {
            $this->assertSame(1, DB::table('accion')->where('operacion', $operation)->count(), $operation);
            $this->assertSame($type, DB::table('accion')->where('operacion', $operation)->value('tipo_operacion'));
        }
    }

    public function test_repetir_la_migracion_conserva_las_solicitudes(): void
    {
        $id = $this->insertRectificationRequest($this->recordId);

        $this->migration()->up();

        $this->assertSame(1, DB::table('solicitud_rectificacion')->where('id_solicitud_rect', $id)->count());
    }

    public function test_crea_el_indice_unico_parcial_de_una_pendiente_por_registro(): void
    {
        $definition = DB::table('pg_indexes')->where('tablename', 'solicitud_rectificacion')
            ->where('indexname', 'uq_solicitud_rect_pendiente_registro')->value('indexdef');

        $this->assertStringContainsString('UNIQUE', $definition);
        $this->assertStringContainsString('(id_cr_estudiante)', $definition);
        $this->assertStringContainsString('PENDIENTE', $definition);
        $this->assertNotNull(DB::table('pg_indexes')->where('indexname', 'idx_solicitud_rect_docente_estado')->first());
        $this->assertNotNull(DB::table('pg_indexes')->where('indexname', 'idx_solicitud_rect_registro_fecha')->first());
    }

    public function test_acepta_una_fila_valida_por_cada_estado(): void
    {
        $valid = [
            ['estado' => 'PENDIENTE'],
            ['estado' => 'ACEPTADA', 'fecha_respuesta' => now()],
            ['estado' => 'RECHAZADA', 'fecha_respuesta' => now(), 'motivo_rechazo' => 'Motivo del rechazo.',
                'evidencia_public_id' => 'evidencias/rectificaciones/a.png'],
            ['estado' => 'VENCIDA'],
        ];

        foreach ($valid as $index => $overrides) {
            $recordId = $index === 0
                ? $this->recordId
                : $this->insertApprovedRecord($this->context)['id_cr_estudiante'];
            DB::table('solicitud_rectificacion')->insert($this->row($overrides + ['id_cr_estudiante' => $recordId]));
        }

        $this->assertSame(4, DB::table('solicitud_rectificacion')->count());
    }

    /** @return array<string, array{0: array<string, mixed>}> */
    public function invalidRows(): array
    {
        return [
            'estado desconocido' => [['estado' => 'CANCELADA']],
            'pendiente con fecha de respuesta' => [['fecha_respuesta' => '2026-10-20 10:00:00+00']],
            'pendiente con motivo de rechazo' => [['motivo_rechazo' => 'Motivo.']],
            'pendiente con evidencia' => [['evidencia_public_id' => 'evidencias/rectificaciones/a.png']],
            'aceptada sin fecha de respuesta' => [['estado' => 'ACEPTADA']],
            'aceptada con motivo de rechazo' => [
                ['estado' => 'ACEPTADA', 'fecha_respuesta' => '2026-10-20 10:00:00+00', 'motivo_rechazo' => 'Motivo.'],
            ],
            'rechazada sin motivo' => [['estado' => 'RECHAZADA', 'fecha_respuesta' => '2026-10-20 10:00:00+00']],
            'rechazada con motivo en blanco' => [[
                'estado' => 'RECHAZADA', 'fecha_respuesta' => '2026-10-20 10:00:00+00', 'motivo_rechazo' => '   ',
            ]],
            'rechazada sin fecha de respuesta' => [['estado' => 'RECHAZADA', 'motivo_rechazo' => 'Motivo.']],
            'vencida con fecha de respuesta' => [
                ['estado' => 'VENCIDA', 'fecha_respuesta' => '2026-10-20 10:00:00+00'],
            ],
            'motivo de solicitud en blanco' => [['motivo_solicitud' => '   ']],
            'vence antes de enviarse' => [['vence_en' => '2020-01-01 00:00:00+00']],
        ];
    }

    /** @dataProvider invalidRows */
    public function test_las_restricciones_rechazan_filas_incoherentes(array $overrides): void
    {
        $this->assertRejected(
            fn () => DB::table('solicitud_rectificacion')->insert($this->row($overrides)),
            'La fila incoherente debió rechazarse.'
        );
    }

    public function test_la_deteccion_exige_una_sola_clave_y_es_unica_por_cuenta_y_por_registro(): void
    {
        $teacherId = $this->approverId($this->recordId);

        $this->assertRejected(
            fn () => DB::table('docente_inactivo_deteccion')->insert(['detectado_en' => now()]),
            'Una detección sin clave debió rechazarse.'
        );
        $this->assertRejected(
            fn () => DB::table('docente_inactivo_deteccion')->insert([
                'id_usuario' => $teacherId, 'id_cr_estudiante' => $this->recordId,
            ]),
            'Una detección con las dos claves debió rechazarse.'
        );

        DB::table('docente_inactivo_deteccion')->insert(['id_usuario' => $teacherId]);

        try {
            DB::transaction(fn () => DB::table('docente_inactivo_deteccion')->insert(['id_usuario' => $teacherId]));
            $this->fail('Dos detecciones de la misma cuenta debieron rechazarse.');
        } catch (QueryException $exception) {
            $this->assertSame('23505', (string) $exception->getCode());
        }
    }

    public function test_el_down_aborta_sin_tocar_nada_si_hay_solicitudes(): void
    {
        $this->insertRectificationRequest($this->recordId);

        try {
            $this->migration()->down();
            $this->fail('El down debía abortar porque hay solicitudes.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('No se modificó nada', $exception->getMessage());
        }

        $this->assertTrue(Schema::hasTable('solicitud_rectificacion'));
        $this->assertSame(1, DB::table('accion')->where('operacion', 'SOLICITAR_RECTIFICACION')->count());
    }

    public function test_el_down_aborta_si_hay_detecciones(): void
    {
        DB::table('docente_inactivo_deteccion')->insert(['id_usuario' => $this->approverId($this->recordId)]);

        $this->expectException(RuntimeException::class);

        $this->migration()->down();
    }

    public function test_el_down_aborta_si_la_bitacora_usa_alguna_de_las_acciones(): void
    {
        app(\App\Services\Security\AuditLogService::class)->registrar(
            'SOLICITAR_RECTIFICACION',
            'solicitud_rectificacion',
            null,
            ['id_cr_estudiante' => $this->recordId],
            $this->administratorId
        );

        $this->expectException(RuntimeException::class);

        $this->migration()->down();
    }

    public function test_el_down_sin_historial_quita_las_tablas_y_las_acciones(): void
    {
        $this->migration()->down();

        $this->assertFalse(Schema::hasTable('solicitud_rectificacion'));
        $this->assertFalse(Schema::hasTable('docente_inactivo_deteccion'));
        $this->assertSame(0, DB::table('accion')->whereIn('operacion', array_keys(self::ACTIONS))->count());
    }

    public function test_los_valores_por_defecto_de_la_configuracion(): void
    {
        $this->assertSame(7, config('sciem.rectificacion.dias_vencimiento'));
        $this->assertSame(180, config('sciem.rectificacion.dias_docente_inactivo'));
        $this->assertSame([Role::DOCENTE, Role::INVITADO], config('sciem.access.risk_rectify_confirm'));
        $this->assertSame([Role::ADMINISTRADOR], config('sciem.access.risk_rectify'));
    }
}
