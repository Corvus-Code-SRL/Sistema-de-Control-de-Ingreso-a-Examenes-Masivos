<?php

namespace Tests\Feature\Exams\RoomDistribution;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Esquema aditivo y acciones de bitácora de la distribución opcional (HU-031). */
class RoomDistributionSchemaTest extends TestCase
{
    use DatabaseTransactions;

    public function test_las_acciones_existen_una_sola_vez(): void
    {
        foreach (['GUARDAR_DISTRIBUCION', 'DEJAR_USAR_DISTRIBUCION', 'INGRESO_CON_AVISO_AMBIENTE'] as $operation) {
            $this->assertSame(1, DB::table('accion')->where('operacion', $operation)->count());
        }
    }

    public function test_las_columnas_nuevas_admiten_nulos(): void
    {
        $columns = [
            ['examen', 'distribucion_suspendida_en'],
            ['examen', 'id_usuario_suspende_distribucion'],
            ['examen_estudiante', 'id_ambiente_planificado'],
        ];

        foreach ($columns as [$table, $column]) {
            $this->assertSame('YES', DB::selectOne(
                "select is_nullable from information_schema.columns
                 where table_schema = 'public' and table_name = ? and column_name = ?",
                [$table, $column]
            )->is_nullable);
        }
    }
}
