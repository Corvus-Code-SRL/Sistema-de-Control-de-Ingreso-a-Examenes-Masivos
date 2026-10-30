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

    public function test_dos_ejecuciones_dejan_diecisiete_acciones_sin_duplicados(): void
    {
        $this->seed(ActionSeeder::class);
        $afterFirst = $this->catalog();

        $this->seed(ActionSeeder::class);

        $this->assertCount(17, $afterFirst);
        $this->assertSame($afterFirst, $this->catalog());
        $this->assertSame(17, DB::table('accion')->distinct()->count('operacion'));
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
        DB::table('accion')
            ->where('operacion', 'APROBAR_REPORTE')
            ->update([
                'descripcion' => 'Otra descripción',
            ]);

        $this->seed(ActionSeeder::class);

        $this->assertSame(
            1,
            DB::table('accion')
                ->where('operacion', 'APROBAR_REPORTE')
                ->count()
        );

        $this->assertSame(
            'Aprobación de un reporte de falta',
            DB::table('accion')
                ->where('operacion', 'APROBAR_REPORTE')
                ->value('descripcion')
        );

        $this->assertSame(17, DB::table('accion')->count());
    }

    public function test_conserva_inicio_y_las_dos_acciones_de_situaciones(): void
    {
        $this->seed(ActionSeeder::class);
        $this->seed(ActionSeeder::class);

        $this->assertSame(
            [
                'CAMBIAR_AMBIENTE' => 'UPDATE',
                'INICIAR' => 'UPDATE',
                'OBSERVAR_IDENTIDAD' => 'INSERT',
            ],
            DB::table('accion')
                ->whereIn('operacion', ['INICIAR', 'CAMBIAR_AMBIENTE', 'OBSERVAR_IDENTIDAD'])
                ->orderBy('operacion')
                ->pluck('tipo_operacion', 'operacion')
                ->all()
        );
        $this->assertSame(17, DB::table('accion')->count());
    }

}