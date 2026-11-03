<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSituacionExamenTable extends Migration
{
    
    public function up()
    {
        Schema::create('situacion_examen', function (Blueprint $table) {
            $table->increments('id_situacion');
            $table->integer('id_examen');
            $table->integer('id_estudiante');
            $table->string('tipo', 30);            
            $table->integer('id_ambiente_origen');
            $table->integer('id_ambiente_destino')->nullable();
            $table->text('motivo');
            $table->timestampTz('registrado_en');
            $table->uuid('id_usuario_registrante');

            $table->foreign(['id_examen', 'id_estudiante'])
                ->references(['id_examen', 'id_estudiante'])
                ->on('examen_estudiante');

            $table->foreign(['id_examen', 'id_ambiente_origen'])
                ->references(['id_examen', 'id_ambiente'])
                ->on('examen_ambiente');
            
            $table->foreign(['id_examen', 'id_ambiente_destino'])
                ->references(['id_examen', 'id_ambiente'])
                ->on('examen_ambiente');
            
            $table->foreign('id_usuario_registrante')
                ->references('id_usuario')
                ->on('usuario');

            $table->index(
                ['id_examen', 'registrado_en', 'id_situacion'], 
                'idx_situacion_examen_fecha'
            );

            $table->index(
                ['id_examen', 'id_estudiante'], 
                'idx_situacion_examen_estudiante'
            );
        });

        DB::statement("
            ALTER TABLE situacion_examen
            ADD CONSTRAINT chk_situacion_examen_tipo
            CHECK (tipo IN ('CAMBIO_AMBIENTE', 'IDENTIFICACION'))
        ");

        DB::statement("
            ALTER TABLE situacion_examen
            ADD CONSTRAINT chk_situacion_examen_destino
            CHECK (
                (tipo = 'CAMBIO_AMBIENTE' 
                    AND id_ambiente_destino IS NOT NULL 
                    AND id_ambiente_destino <> id_ambiente_origen)
                OR
                (tipo = 'IDENTIFICACION' AND id_ambiente_destino IS NULL)
            )
        ");

        DB::statement("
            ALTER TABLE situacion_examen
            ADD CONSTRAINT chk_situacion_examen_motivo
            CHECK (
                (tipo = 'CAMBIO_AMBIENTE' AND char_length(trim(motivo)) >= 1)
                OR
                (tipo = 'IDENTIFICACION' AND char_length(trim(motivo)) >= 10)
            )
        ");
    }

    public function down()
    {
        Schema::dropIfExists('situacion_examen');
    }
}
