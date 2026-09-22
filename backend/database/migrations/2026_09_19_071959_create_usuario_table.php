<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateUsuarioTable extends Migration
{
    public function up()
    {
        Schema::create('usuario', function (Blueprint $table) {
            $table->uuid('id_usuario')->primary();
            $table->string('nombre', 50);
            $table->string('apellido_paterno', 30);
            $table->string('apellido_materno', 30)->nullable();
            $table->string('correo', 100);
            $table->string('contrasenia', 256);
            $table->string('cod_sis', 15);

            $table->unique('cod_sis', 'unq_usuario_cod_sis');
            $table->unique('correo', 'unq_usuario_correo');
        });

        DB::statement("
            ALTER TABLE public.usuario
            ALTER COLUMN id_usuario SET DEFAULT gen_random_uuid()
        ");

        DB::statement("
            ALTER TABLE public.usuario
            ADD COLUMN estado public.estado_registro NOT NULL DEFAULT 'ACTIVO'
        ");

        DB::statement("CREATE INDEX idx_usuario_estado ON public.usuario(estado)");
    }

    public function down()
    {
        Schema::dropIfExists('usuario');
    }
}