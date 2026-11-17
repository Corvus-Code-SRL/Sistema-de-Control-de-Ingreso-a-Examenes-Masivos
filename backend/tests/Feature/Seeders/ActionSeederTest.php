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

    public function test_dos_ejecuciones_dejan_treinta_y_una_acciones_sin_duplicados(): void
    {
        $this->seed(ActionSeeder::class);
        $afterFirst = $this->catalog();

        $this->seed(ActionSeeder::class);

        $this->assertCount(31, $afterFirst);
        $this->assertSame($afterFirst, $this->catalog());
        $this->assertSame(31, DB::table('accion')->distinct()->count('operacion'));
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

    public function test_incluye_las_acciones_de_responder_una_invitacion(): void
    {
        $this->seed(ActionSeeder::class);

        $this->assertSame(
            [
                'ACEPTAR_INVITACION' => ['UPDATE', 'Aceptación de una invitación a colaborar en un examen'],
                'RECHAZAR_INVITACION' => ['UPDATE', 'Rechazo de una invitación a colaborar en un examen'],
            ],
            DB::table('accion')
                ->whereIn('operacion', ['ACEPTAR_INVITACION', 'RECHAZAR_INVITACION'])
                ->orderBy('operacion')
                ->get()
                ->mapWithKeys(fn ($row) => [$row->operacion => [$row->tipo_operacion, $row->descripcion]])
                ->all()
        );
    }

    public function test_incluye_las_acciones_del_ingreso_con_retraso(): void
    {
        $this->seed(ActionSeeder::class);

        $this->assertSame(
            [
                'AUTORIZAR_INGRESO_RETRASO' => 'UPDATE',
                'RECHAZAR_INGRESO_RETRASO' => 'UPDATE',
                'REGISTRAR_INGRESO_RETRASO' => 'INSERT',
                'SOLICITAR_INGRESO_RETRASO' => 'INSERT',
            ],
            DB::table('accion')
                ->where('operacion', 'like', '%_INGRESO_RETRASO')
                ->orderBy('operacion')
                ->pluck('tipo_operacion', 'operacion')
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

        $this->assertSame(31, DB::table('accion')->count());
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
        $this->assertSame(31, DB::table('accion')->count());
    }

    public function test_las_acciones_nuevas_van_al_final_del_catalogo(): void
    {
        $this->seed(ActionSeeder::class);

        $lastSeven = DB::table('accion')->orderByDesc('id_accion')->limit(7)->pluck('operacion')
            ->reverse()->values()->all();

        $this->assertSame(
            [
                'GUARDAR_HABILITADOS', 'ACEPTAR_INVITACION', 'RECHAZAR_INVITACION',
                'REGISTRAR_ESTUDIANTE_EXCEPCIONAL', 'INICIAR_CRONOMETRO', 'AJUSTAR_CRONOMETRO',
                'GUARDAR_DISTRIBUCION',
            ],
            $lastSeven
        );
    }

    public function test_sembrar_en_una_base_que_ya_tiene_el_catalogo_agrega_el_excepcional_sin_mover_los_ids(): void
    {
        $this->seed(ActionSeeder::class);
        DB::table('accion')->where('operacion', 'REGISTRAR_ESTUDIANTE_EXCEPCIONAL')->delete();
        $existing = DB::table('accion')->orderBy('id_accion')->pluck('operacion', 'id_accion')->all();

        $this->seed(ActionSeeder::class);
        $this->seed(ActionSeeder::class);

        $afterwards = DB::table('accion')->orderBy('id_accion')->pluck('operacion', 'id_accion')->all();
        $newId = array_search('REGISTRAR_ESTUDIANTE_EXCEPCIONAL', $afterwards, true);

        $this->assertSame($existing, array_diff_key($afterwards, [$newId => true]));
        $this->assertGreaterThan(max(array_keys($existing)), $newId);
        $this->assertSame(1, DB::table('accion')->where('operacion', 'REGISTRAR_ESTUDIANTE_EXCEPCIONAL')->count());
    }

    public function test_registra_el_retiro_del_examen_sin_duplicados(): void
    {
        $this->seed(ActionSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->assertSame(1, DB::table('accion')->where('operacion', 'EXPULSAR_ESTUDIANTE')->count());
        $this->assertDatabaseHas('accion', [
            'operacion' => 'EXPULSAR_ESTUDIANTE', 'tipo_operacion' => 'UPDATE',
            'descripcion' => 'Retiro de un estudiante durante el examen',
        ]);
    }
}
