<?php

namespace Tests\Feature\RiskCenter\Academic\Rectification;

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
 * HU-062: la migración que agrega a central_riesgo las columnas de rectificación y registra la acción
 * RECTIFICAR_REGISTRO es aditiva, se puede repetir y no se deja revertir si perdería historial.
 */
class CentralRiesgoRectificationMigrationTest extends TestCase
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

        require_once database_path('migrations/2026_11_14_000000_add_rectification_to_central_riesgo.php');

        $this->seedSecurityAccounts();
        $this->context = $this->createReportingContext();
        $this->recordId = $this->insertApprovedRecord($this->context)['id_cr_estudiante'];
    }

    private function migration(): \AddRectificationToCentralRiesgo
    {
        return new \AddRectificationToCentralRiesgo();
    }

    public function test_agrega_las_tres_columnas_nulas_y_no_crea_otra_tabla(): void
    {
        $this->assertTrue(Schema::hasColumns('central_riesgo', [
            'rectificado_en', 'id_usuario_rectificador', 'motivo_rectificacion',
        ]));
        $this->assertFalse(Schema::hasTable('central_riesgo_version'));

        $row = $this->recordRow($this->recordId);
        $this->assertNull($row['rectificado_en']);
        $this->assertNull($row['id_usuario_rectificador']);
        $this->assertNull($row['motivo_rectificacion']);
    }

    public function test_no_toca_el_reporte_original(): void
    {
        $this->assertFalse(Schema::hasColumn('reporte_estudiante', 'rectificado_en'));
        $this->assertFalse(Schema::hasColumn('reporte_estudiante', 'motivo_rectificacion'));
    }

    public function test_crea_los_indices_parciales_de_registros_activos(): void
    {
        $indexes = DB::table('pg_indexes')->where('tablename', 'central_riesgo')->pluck('indexdef', 'indexname');

        $active = 'WHERE (rectificado_en IS NULL)';

        $this->assertStringContainsString($active, $indexes['idx_central_riesgo_activo_estudiante']);
        $this->assertStringContainsString('(id_estudiante)', $indexes['idx_central_riesgo_activo_estudiante']);
        $this->assertStringContainsString($active, $indexes['idx_central_riesgo_activo_fecha']);
        $this->assertArrayHasKey('idx_central_riesgo_estudiante', $indexes->all());
    }

    public function test_acepta_las_tres_columnas_juntas(): void
    {
        $this->voidRecord($this->recordId);

        $row = $this->recordRow($this->recordId);
        $this->assertNotNull($row['rectificado_en']);
        $this->assertSame($this->administratorId, $row['id_usuario_rectificador']);
    }

    /** @return array<string, array{0: array<string, mixed>}> */
    public function incompleteRectifications(): array
    {
        $at = '2026-10-20 15:00:00+00';

        return [
            'solo la fecha' => [['rectificado_en' => $at]],
            'solo el motivo' => [['motivo_rectificacion' => 'Motivo suficiente.']],
            'fecha y motivo sin administrador' => [
                ['rectificado_en' => $at, 'motivo_rectificacion' => 'Motivo suficiente.'],
            ],
            'motivo vacío' => [['rectificado_en' => $at, 'motivo_rectificacion' => '   ', 'con_admin' => true]],
        ];
    }

    /** @dataProvider incompleteRectifications */
    public function test_el_check_exige_las_tres_columnas_juntas(array $values): void
    {
        if (($values['con_admin'] ?? false) === true) {
            unset($values['con_admin']);
            $values['id_usuario_rectificador'] = $this->administratorId;
        }

        $this->expectException(QueryException::class);

        DB::table('central_riesgo')->where('id_cr_estudiante', $this->recordId)->update($values);
    }

    public function test_el_administrador_debe_ser_un_usuario_existente(): void
    {
        $this->expectException(QueryException::class);

        DB::table('central_riesgo')->where('id_cr_estudiante', $this->recordId)->update([
            'rectificado_en' => '2026-10-20 15:00:00+00',
            'id_usuario_rectificador' => '00000000-0000-0000-0000-000000000000',
            'motivo_rectificacion' => 'Motivo suficiente.',
        ]);
    }

    public function test_registra_la_accion_una_sola_vez_aunque_se_repita(): void
    {
        $this->migration()->up();
        $this->migration()->up();

        $this->assertSame(1, DB::table('accion')->where('operacion', 'RECTIFICAR_REGISTRO')->count());
        $this->assertSame(
            'UPDATE',
            DB::table('accion')->where('operacion', 'RECTIFICAR_REGISTRO')->value('tipo_operacion')
        );
    }

    public function test_repetir_la_migracion_no_borra_ni_cambia_registros_rectificados(): void
    {
        $this->voidRecord($this->recordId);
        $before = $this->recordRow($this->recordId);

        $this->migration()->up();

        $this->assertSame($before, $this->recordRow($this->recordId));
    }

    public function test_el_down_aborta_sin_tocar_nada_si_hay_registros_rectificados(): void
    {
        $this->voidRecord($this->recordId);
        $before = $this->recordRow($this->recordId);

        try {
            $this->migration()->down();
            $this->fail('El down debía abortar porque hay registros rectificados.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('No se modificó nada', $exception->getMessage());
        }

        $this->assertTrue(Schema::hasColumn('central_riesgo', 'rectificado_en'));
        $this->assertSame($before, $this->recordRow($this->recordId));
        $this->assertSame(1, DB::table('accion')->where('operacion', 'RECTIFICAR_REGISTRO')->count());
    }

    public function test_el_down_aborta_si_la_bitacora_usa_la_accion(): void
    {
        $this->rectify($this->recordId)->assertOk();
        DB::table('central_riesgo')->where('id_cr_estudiante', $this->recordId)->update([
            'rectificado_en' => null,
            'id_usuario_rectificador' => null,
            'motivo_rectificacion' => null,
        ]);

        $this->expectException(RuntimeException::class);

        $this->migration()->down();
    }
}
