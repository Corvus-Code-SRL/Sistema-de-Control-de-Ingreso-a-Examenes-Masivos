<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Planificación de ambientes independiente del ambiente de ingreso real (HU-031). */
class CreateDistribucionAmbienteTable extends Migration
{
    private const ACTION = 'GUARDAR_DISTRIBUCION';

    public function up(): void
    {
        Schema::create('distribucion_ambiente', function (Blueprint $table): void {
            $table->integer('id_examen');
            $table->integer('id_estudiante');
            $table->integer('id_ambiente');
            $table->uuid('id_usuario_asignador');
            $table->timestampTz('asignado_en', 6);

            $table->index(['id_examen', 'id_ambiente'], 'idx_distribucion_ambiente_examen_ambiente');
            $table->foreign('id_examen', 'fk_distribucion_ambiente_examen')
                ->references('id_examen')->on('examen');
            $table->foreign('id_estudiante', 'fk_distribucion_ambiente_estudiante')
                ->references('id_estudiante')->on('estudiante');
            // Quitar un ambiente del examen no debe quedar bloqueado por una planificación anterior.
            $table->foreign('id_ambiente', 'fk_distribucion_ambiente_ambiente')
                ->references('id_ambiente')->on('ambiente');
            $table->foreign('id_usuario_asignador', 'fk_distribucion_ambiente_usuario')
                ->references('id_usuario')->on('usuario');
        });

        // Laravel 8 ignora el nombre de la clave primaria en PostgreSQL.
        DB::statement('ALTER TABLE distribucion_ambiente ADD CONSTRAINT pk_distribucion_ambiente
            PRIMARY KEY (id_examen, id_estudiante)');

        // accion.operacion no es UNIQUE: se inserta solo si falta para convivir con ActionSeeder.
        DB::statement(
            'INSERT INTO accion (operacion, tipo_operacion, descripcion) SELECT ?, ?, ? '
            . 'WHERE NOT EXISTS (SELECT 1 FROM accion WHERE operacion = ?)',
            [self::ACTION, 'UPDATE', 'Guardado de la distribución de habilitados por ambiente', self::ACTION]
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('distribucion_ambiente');
        DB::table('accion')->where('operacion', self::ACTION)
            ->whereNotExists(fn ($log) => $log->selectRaw('1')->from('log')->whereColumn('log.id_accion', 'accion.id_accion'))
            ->delete();
    }
}
