<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateAccionTable extends Migration
{
    public function up()
    {
        Schema::create('accion', function (Blueprint $table) {
            $table->increments('id_accion');
            $table->string('operacion', 100);
            $table->string('tipo_operacion', 10);
            $table->text('descripcion')->nullable();
        });

        DB::statement("CREATE INDEX idx_accion_tipo_operacion ON public.accion(tipo_operacion)");
    }

    public function down()
    {
        Schema::dropIfExists('accion');
    }
}