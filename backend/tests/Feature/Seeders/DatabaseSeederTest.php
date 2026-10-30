<?php

namespace Tests\Feature\Seeders;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Una base recién migrada (el esquema que TestCase arma con DROP SCHEMA + migrate) queda con los
 * catálogos del sistema al correr `db:seed`, sin pasos manuales: 4 tipos de falta y 17 acciones.
 */
class DatabaseSeederTest extends TestCase
{
    use DatabaseTransactions;

    private function counts(): array
    {
        return [
            'tipo_falta' => DB::table('tipo_falta')->count(),
            'accion' => DB::table('accion')->count(),
            'rol' => DB::table('rol')->count(),
            'tipo_examen' => DB::table('tipo_examen')->count(),
        ];
    }

    public function test_el_seeder_general_carga_los_cuatro_tipos_de_falta_y_las_diecisiete_acciones(): void
    {
        $this->assertSame(0, DB::table('tipo_falta')->count());
        $this->assertSame(17, DB::table('accion')->count());

        $this->seed(DatabaseSeeder::class);

        $counts = $this->counts();
        $this->assertSame(4, $counts['tipo_falta']);
        $this->assertSame(17, $counts['accion']);
        $this->assertSame(
            ['Conducta indebida', 'Copia en examen', 'Material no permitido', 'Suplantación de identidad'],
            DB::table('tipo_falta')->orderBy('nombre')->pluck('nombre')->all()
        );
    }

    public function test_repetir_el_seeder_general_no_duplica_nada(): void
    {
        $this->seed(DatabaseSeeder::class);
        $first = $this->counts();

        $this->seed(DatabaseSeeder::class);

        $this->assertSame($first, $this->counts());
    }
}