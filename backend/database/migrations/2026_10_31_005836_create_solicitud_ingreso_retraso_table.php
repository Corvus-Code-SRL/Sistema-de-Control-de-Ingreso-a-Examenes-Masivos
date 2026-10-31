<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HU-037: solicitudes de ingreso con retraso. El auxiliar solicita y el docente autoriza o
 * rechaza; la fila guarda quién resolvió y cuándo, así examen_estudiante no cambia.
 *
 * El índice parcial impide dos solicitudes PENDIENTE del mismo estudiante en un examen.
 */
class CreateSolicitudIngresoRetrasoTable extends Migration
{
    public function up()
    {
        Schema::create('solicitud_ingreso_retraso', function (Blueprint $table) {
            $table->bigIncrements('id_solicitud');
            $table->integer('id_examen');
            $table->integer('id_estudiante');
            $table->integer('id_ambiente');
            $table->uuid('id_usuario_solicitante');
            $table->uuid('id_usuario_resolutor')->nullable();
            $table->string('estado', 10)->default('PENDIENTE');
            $table->timestampTz('solicitado_en');
            $table->timestampTz('resuelto_en')->nullable();

            $table->foreign('id_examen')->references('id_examen')->on('examen');
            $table->foreign('id_estudiante')->references('id_estudiante')->on('estudiante');
            $table->foreign('id_usuario_solicitante')->references('id_usuario')->on('usuario');
            $table->foreign('id_usuario_resolutor')->references('id_usuario')->on('usuario');
            $table->index(['id_examen', 'estado', 'solicitado_en'], 'idx_solicitud_retraso_examen_estado');
        });

        DB::statement('ALTER TABLE solicitud_ingreso_retraso ADD CONSTRAINT fk_solicitud_retraso_examen_ambiente
            FOREIGN KEY (id_examen, id_ambiente) REFERENCES examen_ambiente (id_examen, id_ambiente)');
        DB::statement("ALTER TABLE solicitud_ingreso_retraso ADD CONSTRAINT chk_solicitud_retraso_estado
            CHECK (estado IN ('PENDIENTE', 'AUTORIZADA', 'RECHAZADA'))");
        DB::statement("ALTER TABLE solicitud_ingreso_retraso ADD CONSTRAINT chk_solicitud_retraso_resolucion
            CHECK (
                (estado = 'PENDIENTE' AND id_usuario_resolutor IS NULL AND resuelto_en IS NULL)
                OR (estado <> 'PENDIENTE' AND id_usuario_resolutor IS NOT NULL AND resuelto_en IS NOT NULL)
            )");
        DB::statement("CREATE UNIQUE INDEX uq_solicitud_retraso_pendiente
            ON solicitud_ingreso_retraso (id_examen, id_estudiante) WHERE estado = 'PENDIENTE'");
    }

    public function down()
    {
        Schema::dropIfExists('solicitud_ingreso_retraso');
    }
}
