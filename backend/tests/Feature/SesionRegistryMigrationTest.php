<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Migración 2026_11_26_000002 (registro de sesiones). El DDL de PostgreSQL es transaccional: lo que se
 * revierte y se vuelve a aplicar aquí se descarta al terminar cada prueba.
 */
class SesionRegistryMigrationTest extends TestCase
{
    use DatabaseTransactions;

    private const MIGRATION = '2026_11_26_000002_rebuild_sesion_registry.php';

    protected function setUp(): void
    {
        parent::setUp();

        require_once database_path('migrations/' . self::MIGRATION);
    }

    private function migration(): \RebuildSesionRegistry
    {
        return new \RebuildSesionRegistry();
    }

    /** @param array<string, mixed> $values */
    private function insertSession(array $values): void
    {
        DB::table('sesion')->insert($values + [
            'id_usuario' => User::factory()->create()->id_usuario,
            'ip_direccion' => '10.0.0.5',
            'hora_ingreso' => '2026-11-26 08:00:00+00',
        ]);
    }

    public function test_quita_las_columnas_del_proceso_y_agrega_las_de_la_sesion(): void
    {
        $this->assertFalse(Schema::hasColumn('sesion', 'pid'));
        $this->assertFalse(Schema::hasColumn('sesion', 'num_puerto'));

        foreach (['id_token', 'agente', 'hora_salida', 'motivo_salida'] as $column) {
            $this->assertTrue(Schema::hasColumn('sesion', $column), $column);
        }
    }

    public function test_las_filas_anteriores_quedan_cerradas_como_expiradas(): void
    {
        $this->migration()->down();
        DB::table('sesion')->insert([
            'id_usuario' => User::factory()->create()->id_usuario,
            'pid' => 4321,
            'ip_direccion' => '10.0.0.5',
            'num_puerto' => 5432,
        ]);

        $this->migration()->up();

        $row = DB::table('sesion')->first();
        $this->assertSame('EXPIRADA', $row->motivo_salida);
        $this->assertSame($row->hora_ingreso, $row->hora_salida);
    }

    public function test_el_motivo_de_salida_solo_admite_los_tres_valores(): void
    {
        $this->expectException(QueryException::class);

        $this->insertSession(['hora_salida' => '2026-11-26 09:00:00+00', 'motivo_salida' => 'OTRO']);
    }

    public function test_hora_y_motivo_de_salida_van_juntos(): void
    {
        $this->expectException(QueryException::class);

        $this->insertSession(['hora_salida' => '2026-11-26 09:00:00+00']);
    }

    public function test_la_salida_no_puede_ser_anterior_al_ingreso(): void
    {
        $this->expectException(QueryException::class);

        $this->insertSession(['hora_salida' => '2026-11-26 07:00:00+00', 'motivo_salida' => 'CIERRE']);
    }
}
