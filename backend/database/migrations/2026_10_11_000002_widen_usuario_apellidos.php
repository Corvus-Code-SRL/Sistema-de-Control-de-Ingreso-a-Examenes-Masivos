<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * HU-001: el SIS devuelve los apellidos en un solo campo, que puede superar los 30 caracteres de cada
 * columna. Se amplían a 60 solo en usuario; estudiante y postulante no se tocan.
 */
class WidenUsuarioApellidos extends Migration
{
    public function up()
    {
        DB::statement('ALTER TABLE usuario ALTER COLUMN apellido_paterno TYPE varchar(60)');
        DB::statement('ALTER TABLE usuario ALTER COLUMN apellido_materno TYPE varchar(60)');
    }

    /** Estrechar con apellidos de más de 30 caracteres los truncaría: en ese caso no se revierte. */
    public function down()
    {
        $tooLong = DB::table('usuario')
            ->whereRaw('char_length(apellido_paterno) > 30 OR char_length(apellido_materno) > 30')
            ->exists();

        if ($tooLong) {
            throw new RuntimeException(
                'Hay cuentas con apellidos de más de 30 caracteres: revertir la migración los truncaría.'
            );
        }

        DB::statement('ALTER TABLE usuario ALTER COLUMN apellido_paterno TYPE varchar(30)');
        DB::statement('ALTER TABLE usuario ALTER COLUMN apellido_materno TYPE varchar(30)');
    }
}
