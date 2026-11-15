<?php

namespace Tests\Feature\RiskCenter\Academic\Rectification;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\Concerns\SeedsRectifications;
use Tests\Concerns\SeedsRiskCenterRecords;
use Tests\Concerns\SeedsSecurityAccounts;
use Tests\TestCase;

/**
 * HU-062: la migración que crea central_riesgo_version y registra la acción RECTIFICAR_REGISTRO es aditiva,
 * se puede repetir y no se deja revertir si perdería historial.
 */
class CentralRiesgoVersionMigrationTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsRectifications;
    use SeedsRiskCenterRecords;
    use SeedsSecurityAccounts;

    protected function setUp(): void
    {
        parent::setUp();

        require_once database_path('migrations/2026_11_14_000000_create_central_riesgo_version_table.php');
    }

    private function migration(): \CreateCentralRiesgoVersionTable
    {
        return new \CreateCentralRiesgoVersionTable();
    }

    public function test_crea_la_tabla_con_sus_columnas_e_indices(): void
    {
        $this->assertTrue(Schema::hasTable('central_riesgo_version'));
        $this->assertTrue(Schema::hasColumns('central_riesgo_version', [
            'id_version', 'id_cr_estudiante', 'numero_version', 'id_tipo_falta', 'descripcion', 'motivo',
            'id_usuario_autor', 'fecha_registro', 'es_vigente',
        ]));

        $indexes = DB::table('pg_indexes')
            ->where('tablename', 'central_riesgo_version')
            ->pluck('indexdef', 'indexname');

        $this->assertStringContainsString('WHERE es_vigente', $indexes['unq_central_riesgo_version_vigente']);
        $this->assertStringContainsString('UNIQUE', $indexes['unq_central_riesgo_version_vigente']);
        $this->assertStringContainsString('WHERE es_vigente', $indexes['idx_central_riesgo_version_vigente_tipo']);
    }

    public function test_no_toca_las_tablas_del_registro_original(): void
    {
        $this->assertFalse(Schema::hasColumn('central_riesgo', 'id_tipo_falta'));
        $this->assertFalse(Schema::hasColumn('central_riesgo', 'descripcion'));
        $this->assertFalse(Schema::hasColumn('reporte_estudiante', 'motivo'));
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

    public function test_repetir_la_migracion_no_borra_versiones_guardadas(): void
    {
        $this->seedSecurityAccounts();
        $context = $this->createReportingContext();
        $id = $this->insertApprovedRecord($context)['id_cr_estudiante'];
        $this->rectify($id)->assertCreated();
        $before = $this->versionsOf($id);

        $this->migration()->up();

        $this->assertSame($before, $this->versionsOf($id));
    }

    public function test_el_down_aborta_sin_tocar_nada_si_hay_versiones(): void
    {
        $this->seedSecurityAccounts();
        $context = $this->createReportingContext();
        $id = $this->insertApprovedRecord($context)['id_cr_estudiante'];
        $this->rectify($id)->assertCreated();
        $before = $this->versionsOf($id);

        try {
            $this->migration()->down();
            $this->fail('El down debía abortar porque hay versiones guardadas.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('No se modificó nada', $exception->getMessage());
        }

        $this->assertTrue(Schema::hasTable('central_riesgo_version'));
        $this->assertSame($before, $this->versionsOf($id));
        $this->assertSame(1, DB::table('accion')->where('operacion', 'RECTIFICAR_REGISTRO')->count());
    }
}
