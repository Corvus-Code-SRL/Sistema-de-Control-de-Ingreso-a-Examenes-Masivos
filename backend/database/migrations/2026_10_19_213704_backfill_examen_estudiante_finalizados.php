<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class BackfillExamenEstudianteFinalizados extends Migration
{
    public function up()
    {
        DB::statement("
            INSERT INTO examen_estudiante (
                id_examen,
                id_estudiante,
                id_grupo,
                estado_habilitacion,
                estado_ingreso,
                hora_ingreso
            )
            SELECT
                gx.id_examen,
                gs.id_estudiante,
                MIN(gs.id_grupo),
                'HABILITADO',
                'NO_INGRESO',
                NULL
            FROM grupo_examen gx
            INNER JOIN examen e
                ON e.id_examen = gx.id_examen
            INNER JOIN grupo_estudiante gs
                ON gs.id_grupo = gx.id_grupo
            WHERE e.estado = 'FINALIZADO'
            GROUP BY gx.id_examen, gs.id_estudiante
            ON CONFLICT (id_examen, id_estudiante) DO NOTHING
        ");
    }

    public function down()
    {
        // No se eliminan registros para preservar el historial de ingresos.
    }
}