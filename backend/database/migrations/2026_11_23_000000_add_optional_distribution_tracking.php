<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Distribución opcional (HU-031). Solo agrega columnas nulas; las acciones de bitácora las siembra ActionSeeder.
 * down() quita únicamente las columnas y sus claves foráneas; la bitácora ya escrita no se toca.
 */
class AddOptionalDistributionTracking extends Migration
{
    public function up(): void
    {
        Schema::table('examen', function (Blueprint $table): void {
            $table->timestampTz('distribucion_suspendida_en')->nullable();
            $table->uuid('id_usuario_suspende_distribucion')->nullable();
            $table->foreign('id_usuario_suspende_distribucion', 'fk_examen_usuario_suspende_distribucion')
                ->references('id_usuario')->on('usuario');
        });

        Schema::table('examen_estudiante', function (Blueprint $table): void {
            $table->integer('id_ambiente_planificado')->nullable();
            $table->foreign('id_ambiente_planificado', 'fk_examen_estudiante_ambiente_planificado')
                ->references('id_ambiente')->on('ambiente');
        });
    }

    public function down(): void
    {
        Schema::table('examen_estudiante', function (Blueprint $table): void {
            $table->dropForeign('fk_examen_estudiante_ambiente_planificado');
            $table->dropColumn('id_ambiente_planificado');
        });
        Schema::table('examen', function (Blueprint $table): void {
            $table->dropForeign('fk_examen_usuario_suspende_distribucion');
            $table->dropColumn(['distribucion_suspendida_en', 'id_usuario_suspende_distribucion']);
        });
    }
}
