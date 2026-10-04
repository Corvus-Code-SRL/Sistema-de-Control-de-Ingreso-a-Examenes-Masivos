<?php

namespace Tests\Feature;

use Database\Seeders\ActionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * Migración de datos que elimina las acciones heredadas (Crear, Actualizar, Leer y Desactivar) de `accion`.
 */
class DeleteLegacyAccionRowsTest extends TestCase
{
    use DatabaseTransactions;

    private const LEGACY = [
        ['Crear', 'Escritura'],
        ['Actualizar', 'Editar'],
        ['Leer', 'Consulta'],
        ['Desactivar', 'Baja'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        require_once database_path('migrations/2026_10_04_000000_delete_legacy_accion_rows.php');
    }

    private function migrate(): void
    {
        (new \DeleteLegacyAccionRows())->up();
    }

    private function insertLegacyRows(): void
    {
        foreach (self::LEGACY as [$operation, $type]) {
            DB::table('accion')->insert(['operacion' => $operation, 'tipo_operacion' => $type]);
        }
    }

    private function legacyCount(): int
    {
        return DB::table('accion')->whereIn('operacion', array_column(self::LEGACY, 0))->count();
    }

    /** @return array<int, array<string, mixed>> las acciones del catálogo vigente, ordenadas, tal como están */
    private function currentCatalog(): array
    {
        return DB::table('accion')
            ->whereNotIn('operacion', array_column(self::LEGACY, 0))
            ->orderBy('id_accion')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    public function test_elimina_las_cuatro_filas_heredadas_y_no_toca_las_doce_vigentes(): void
    {
        $this->seed(ActionSeeder::class);
        $this->insertLegacyRows();
        $before = $this->currentCatalog();

        $this->assertCount(12, $before);
        $this->assertSame(4, $this->legacyCount());

        $this->migrate();

        $this->assertSame(0, $this->legacyCount());
        $this->assertSame($before, $this->currentCatalog());
        $this->assertSame(12, DB::table('accion')->count());
    }

    public function test_aborta_sin_borrar_nada_si_la_bitacora_referencia_una_fila_heredada(): void
    {
        $this->seed(ActionSeeder::class);
        $this->insertLegacyRows();
        $referencedId = DB::table('accion')->where('operacion', 'Leer')->value('id_accion');
        $userId = DB::table('usuario')->insertGetId([
            'nombre' => 'Actor',
            'apellido_paterno' => 'Bitacora',
            'correo' => 'actor.bitacora@test.com',
            'contrasenia' => 'x',
            'cod_sis' => '202400777',
            'estado' => 'ACTIVO',
        ], 'id_usuario');
        DB::table('log')->insert(['id_accion' => $referencedId, 'id_usuario' => $userId, 'tabla_afectada' => 'examen']);
        $before = $this->currentCatalog();

        try {
            $this->migrate();
            $this->fail('La migración debía abortar porque log referencia una acción heredada.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('No se borró nada', $exception->getMessage());
            $this->assertStringContainsString('Leer (1 filas de log)', $exception->getMessage());
        }

        $this->assertSame(4, $this->legacyCount());
        $this->assertSame($before, $this->currentCatalog());
        $this->assertSame(1, DB::table('log')->where('id_accion', $referencedId)->count());
    }

    public function test_es_idempotente(): void
    {
        $this->seed(ActionSeeder::class);
        $this->insertLegacyRows();

        $this->migrate();
        $afterFirst = DB::table('accion')->orderBy('id_accion')->get()->map(fn ($row) => (array) $row)->all();

        $this->migrate();

        $this->assertSame($afterFirst, DB::table('accion')->orderBy('id_accion')->get()->map(fn ($row) => (array) $row)->all());
        $this->assertSame(12, DB::table('accion')->count());
    }

    public function test_no_hace_nada_en_una_base_sin_filas_heredadas(): void
    {
        $this->migrate();
        $this->assertSame(0, DB::table('accion')->count());

        $this->seed(ActionSeeder::class);
        $before = $this->currentCatalog();

        $this->migrate();

        $this->assertSame($before, $this->currentCatalog());
    }

    public function test_el_down_no_hace_nada(): void
    {
        $this->seed(ActionSeeder::class);
        $before = $this->currentCatalog();

        (new \DeleteLegacyAccionRows())->down();

        $this->assertSame($before, $this->currentCatalog());
    }
}
