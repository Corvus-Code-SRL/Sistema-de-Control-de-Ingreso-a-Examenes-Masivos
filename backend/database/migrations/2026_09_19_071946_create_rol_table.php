<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateRolTable extends Migration
{
    public function up()
    {
        Schema::create('rol', function (Blueprint $table) {
            $table->increments('id_rol');
            $table->string('nombre_rol', 20);
            $table->text('descripcion')->nullable();
            $table->timestampTz('fecha_registro')->useCurrent();

            $table->unique('nombre_rol', 'unq_rol_nombre');
        });

        DB::statement("
            ALTER TABLE public.rol
            ADD COLUMN estado public.estado_registro NOT NULL DEFAULT 'ACTIVO'
        ");

        DB::statement("CREATE INDEX idx_rol_estado ON public.rol(estado)");
    }

    public function down()
    {
        Schema::dropIfExists('rol');
    }
}