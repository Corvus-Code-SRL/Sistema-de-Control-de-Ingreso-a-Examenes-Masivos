<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Distribución opcional (HU-031). Solo agrega columnas nulas y, si faltan, las acciones de bitácora.
 * down() aborta sin tocar nada si ya hay datos o bitácora de esta funcionalidad.
 */
class AddOptionalDistributionTracking extends Migration
{
    private const ACTIONS = [
        ['DEJAR_USAR_DISTRIBUCION', 'UPDATE', 'El docente deja de usar la distribución de ambientes del examen'],
        ['INGRESO_CON_AVISO_AMBIENTE', 'UPDATE', 'Ingreso registrado pese a un aviso de ambiente distinto o lleno'],
    ];

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

        foreach (self::ACTIONS as [$operation, $type, $description]) {
            DB::statement(
                'INSERT INTO accion (operacion, tipo_operacion, descripcion) SELECT ?, ?, ? '
                . 'WHERE NOT EXISTS (SELECT 1 FROM accion WHERE operacion = ?)',
                [$operation, $type, $description, $operation]
            );
        }
    }

    public function down(): void
    {
        $operations = array_column(self::ACTIONS, 0);
        $hasData = DB::table('examen')->whereNotNull('distribucion_suspendida_en')->exists()
            || DB::table('examen_estudiante')->whereNotNull('id_ambiente_planificado')->exists()
            || DB::table('log')->join('accion', 'accion.id_accion', '=', 'log.id_accion')
                ->whereIn('accion.operacion', $operations)->exists();

        if ($hasData) {
            throw new RuntimeException('No se puede revertir: ya hay datos de la distribución opcional.');
        }

        Schema::table('examen_estudiante', function (Blueprint $table): void {
            $table->dropForeign('fk_examen_estudiante_ambiente_planificado');
            $table->dropColumn('id_ambiente_planificado');
        });
        Schema::table('examen', function (Blueprint $table): void {
            $table->dropForeign('fk_examen_usuario_suspende_distribucion');
            $table->dropColumn(['distribucion_suspendida_en', 'id_usuario_suspende_distribucion']);
        });
        DB::table('accion')->whereIn('operacion', $operations)->delete();
    }
}
