<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateLogTable extends Migration
{
    public function up()
    {
        Schema::create('log', function (Blueprint $table) {
            $table->bigIncrements('id_log');
            $table->unsignedInteger('id_accion')->nullable();
            $table->jsonb('antiguo_valor')->nullable();
            $table->jsonb('nuevo_valor')->nullable();
            $table->timestampTz('fecha_hora')->useCurrent();
            $table->string('tabla_afectada');
            $table->uuid('id_usuario');

            $table->foreign('id_accion', 'fk_log_accion')
                  ->references('id_accion')->on('accion');

            $table->foreign('id_usuario', 'fk_log_usuario')
                  ->references('id_usuario')->on('usuario');
        });

        DB::statement("CREATE INDEX idx_log_accion ON public.log(id_accion)");
        DB::statement("CREATE INDEX idx_log_fecha_hora ON public.log(fecha_hora)");
        DB::statement("CREATE INDEX idx_log_tabla_afectada ON public.log(tabla_afectada)");
        DB::statement("CREATE INDEX idx_log_usuario ON public.log(id_usuario)");
    }

    public function down()
    {
        Schema::dropIfExists('log');
    }
}