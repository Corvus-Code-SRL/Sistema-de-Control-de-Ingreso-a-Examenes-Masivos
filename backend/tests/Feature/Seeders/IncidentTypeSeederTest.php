<?php

namespace Tests\Feature\Seeders;

use Database\Seeders\IncidentTypeSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IncidentTypeSeederTest extends TestCase
{
    use DatabaseTransactions;

    private const NAMES = [
        'Copia en examen',
        'Suplantación de identidad',
        'Material no permitido',
        'Conducta indebida',
    ];

    private function seededTypes(): array
    {
        return DB::table('tipo_falta')->whereIn('nombre', self::NAMES)->orderBy('id_falta')->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    public function test_dos_ejecuciones_dejan_los_cuatro_tipos_sin_duplicados(): void
    {
        $this->seed(IncidentTypeSeeder::class);
        $afterFirst = $this->seededTypes();

        $this->seed(IncidentTypeSeeder::class);

        $this->assertCount(4, $afterFirst);
        $this->assertSame($afterFirst, $this->seededTypes());
        $this->assertSame(4, DB::table('tipo_falta')->count());
    }

    public function test_una_fila_existente_con_otra_descripcion_no_se_modifica(): void
    {
        DB::table('tipo_falta')->insert(['nombre' => 'Copia en examen', 'descripcion' => 'Descripción del equipo.']);

        $this->seed(IncidentTypeSeeder::class);

        $this->assertSame(
            'Descripción del equipo.',
            DB::table('tipo_falta')->where('nombre', 'Copia en examen')->value('descripcion')
        );
        $this->assertSame(1, DB::table('tipo_falta')->where('nombre', 'Copia en examen')->count());
        $this->assertSame(4, DB::table('tipo_falta')->count());
    }
}
