<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Amplía los reportes existentes y conserva el estado de ingreso original al retirar a un alumno. */
class AddInProgressFieldsToReporteEstudiante extends Migration
{
    public function up(): void
    {
        Schema::table('examen_estudiante', function (Blueprint $table): void {
            $table->timestampTz('expulsado_en')->nullable();
        });
        Schema::table('reporte_estudiante', function (Blueprint $table): void {
            $table->string('origen', 20)->default('LEGADO');
            $table->integer('id_ambiente_hecho')->nullable();
            $table->boolean('retiro_examen')->default(false);
            $table->index(['id_examen', 'id_estudiante', 'origen'], 'idx_reporte_examen_estudiante_origen');
        });
        DB::statement("UPDATE reporte_estudiante SET origen = 'CONTROL_INGRESO' WHERE id_intento IS NOT NULL");
        DB::unprepared(<<<'SQL'
        ALTER TABLE reporte_estudiante
            ADD CONSTRAINT fk_reporte_ambiente_hecho
                FOREIGN KEY (id_examen, id_ambiente_hecho) REFERENCES examen_ambiente (id_examen, id_ambiente),
            ADD CONSTRAINT chk_reporte_origen
                CHECK (origen IN ('LEGADO', 'CONTROL_INGRESO', 'EN_CURSO')),
            ADD CONSTRAINT chk_reporte_en_curso
                CHECK (origen <> 'EN_CURSO' OR (id_intento IS NULL AND id_ambiente_hecho IS NOT NULL)),
            ADD CONSTRAINT chk_reporte_retiro
                CHECK (NOT retiro_examen OR origen = 'EN_CURSO');
        SQL);
    }

    public function down(): void
    {
        if (
            DB::table('reporte_estudiante')->where('origen', 'EN_CURSO')->exists()
            || DB::table('examen_estudiante')->whereNotNull('expulsado_en')->exists()
        ) {
            throw new RuntimeException('No se revierte: se perdería el historial de incidencias o retiros del examen.');
        }
        DB::unprepared(<<<'SQL'
        ALTER TABLE reporte_estudiante
            DROP CONSTRAINT fk_reporte_ambiente_hecho,
            DROP CONSTRAINT chk_reporte_origen,
            DROP CONSTRAINT chk_reporte_en_curso,
            DROP CONSTRAINT chk_reporte_retiro;
        SQL);
        Schema::table('reporte_estudiante', function (Blueprint $table): void {
            $table->dropIndex('idx_reporte_examen_estudiante_origen');
            $table->dropColumn(['origen', 'id_ambiente_hecho', 'retiro_examen']);
        });
        Schema::table('examen_estudiante', function (Blueprint $table): void {
            $table->dropColumn('expulsado_en');
        });
    }
}
