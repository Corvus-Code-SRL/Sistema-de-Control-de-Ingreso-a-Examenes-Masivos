<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateUsuarioRolTable extends Migration
{
    public function up()
    {
        Schema::create('usuario_rol', function (Blueprint $table) {
            $table->uuid('id_usuario');
            $table->unsignedInteger('id_rol');
            $table->timestampTz('fecha_inicio')->useCurrent();
            $table->timestampTz('fecha_fin')->nullable();

            $table->primary(['id_usuario', 'id_rol', 'fecha_inicio'], 'pk_usuario_rol');

            $table->foreign('id_usuario', 'fk_usuario_rol_usuario')
                  ->references('id_usuario')->on('usuario');

            $table->foreign('id_rol', 'fk_usuario_rol_rol')
                  ->references('id_rol')->on('rol');
        });

        DB::statement("
            ALTER TABLE public.usuario_rol
            ADD CONSTRAINT chk_usuario_rol_fechas
            CHECK (fecha_fin IS NULL OR fecha_fin >= fecha_inicio)
        ");

        DB::statement("CREATE INDEX idx_usuario_rol_rol ON public.usuario_rol(id_rol)");
        DB::statement("CREATE INDEX idx_usuario_rol_fecha_fin ON public.usuario_rol(fecha_fin)");
    }

    public function down()
    {
        Schema::dropIfExists('usuario_rol');
    }
}