<?php

namespace Tests\Feature\Seeders;

use Database\Seeders\ActionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ActionSeederTest extends TestCase
{
    use DatabaseTransactions;

    /** @return array<int, array<string, mixed>> */
    private function catalog(): array
    {
        return DB::table('accion')->orderBy('id_accion')->get()->map(fn ($row) => (array) $row)->all();
    }

    public function test_dos_ejecuciones_dejan_catorce_acciones_sin_duplicados(): void
    {
        $this->seed(ActionSeeder::class);
        $afterFirst = $this->catalog();

        $this->seed(ActionSeeder::class);

        $this->assertCount(14, $afterFirst);
        $this->assertSame($afterFirst, $this->catalog());
        $this->assertSame(14, DB::table('accion')->distinct()->count('operacion'));
    }

    public function test_incluye_las_acciones_de_aprobacion_y_rechazo_de_reportes(): void
    {
        $this->seed(ActionSeeder::class);

        $this->assertSame(
            [
                'APROBAR_REPORTE' => ['UPDATE', 'Aprobación de un reporte de falta'],
                'RECHAZAR_REPORTE' => ['UPDATE', 'Rechazo de un reporte de falta'],
            ],
            DB::table('accion')
                ->whereIn('operacion', ['APROBAR_REPORTE', 'RECHAZAR_REPORTE'])
                ->orderBy('operacion')
                ->get()
                ->mapWithKeys(fn ($row) => [$row->operacion => [$row->tipo_operacion, $row->descripcion]])
                ->all()
        );
    }

    public function test_corrige_la_descripcion_de_una_accion_existente_sin_duplicarla(): void
    {
        DB::table('accion')->insert([
            'operacion' => 'APROBAR_REPORTE',
            'tipo_operacion' => 'UPDATE',
            'descripcion' => 'Otra descripción',
        ]);

        $this->seed(ActionSeeder::class);

        $this->assertSame(1, DB::table('accion')->where('operacion', 'APROBAR_REPORTE')->count());
        $this->assertSame(
            'Aprobación de un reporte de falta',
            DB::table('accion')->where('operacion', 'APROBAR_REPORTE')->value('descripcion')
        );
        $this->assertSame(14, DB::table('accion')->count());
    }
}
