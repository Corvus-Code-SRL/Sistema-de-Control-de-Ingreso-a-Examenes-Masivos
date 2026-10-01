<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddStudentEntryAuditAndAttempts extends Migration
{
    public function up()
    {
        Schema::table('examen_estudiante', function (Blueprint $table) {
            $table->integer('id_ambiente')->nullable();
            $table->uuid('id_usuario_controlador')->nullable();
            $table->timestampTz('registrado_en')->nullable();
            $table->index(['id_examen', 'registrado_en'], 'idx_ingreso_examen_registrado');
        });

        DB::statement('ALTER TABLE examen_estudiante ADD CONSTRAINT fk_ingreso_examen_ambiente
            FOREIGN KEY (id_examen, id_ambiente) REFERENCES examen_ambiente (id_examen, id_ambiente)');
        DB::statement('ALTER TABLE examen_estudiante ADD CONSTRAINT fk_ingreso_controlador
            FOREIGN KEY (id_usuario_controlador) REFERENCES usuario (id_usuario)');

        Schema::create('intento_ingreso', function (Blueprint $table) {
            $table->bigIncrements('id_intento');
            $table->integer('id_examen');
            $table->integer('id_estudiante')->nullable();
            $table->string('cod_sis', 15)->nullable();
            $table->string('ci_presentado', 10)->nullable();
            $table->integer('id_ambiente');
            $table->uuid('id_usuario_controlador');
            $table->string('motivo', 40);
            $table->text('observacion')->nullable();
            $table->timestampTz('registrado_en');
            $table->foreign('id_examen')->references('id_examen')->on('examen');
            $table->foreign('id_estudiante')->references('id_estudiante')->on('estudiante');
            $table->foreign('id_usuario_controlador')->references('id_usuario')->on('usuario');
            $table->index(['id_examen', 'registrado_en'], 'idx_intento_examen_registrado');
        });

        DB::statement('ALTER TABLE intento_ingreso ADD CONSTRAINT fk_intento_examen_ambiente
            FOREIGN KEY (id_examen, id_ambiente) REFERENCES examen_ambiente (id_examen, id_ambiente)');
    }

    public function down()
    {
        Schema::dropIfExists('intento_ingreso');

        DB::statement('ALTER TABLE examen_estudiante DROP CONSTRAINT IF EXISTS fk_ingreso_examen_ambiente');
        DB::statement('ALTER TABLE examen_estudiante DROP CONSTRAINT IF EXISTS fk_ingreso_controlador');

        Schema::table('examen_estudiante', function (Blueprint $table) {
            $table->dropIndex('idx_ingreso_examen_registrado');
            $table->dropColumn(['id_ambiente', 'id_usuario_controlador', 'registrado_en']);
        });
    }
}
