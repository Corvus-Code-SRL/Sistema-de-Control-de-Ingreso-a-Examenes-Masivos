<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sesión en cada operación de la bitácora (forma 2): columna log.id_sesion.
 *
 * - Nullable: las tareas programadas, los seeders y los comandos no tienen sesión.
 * - La FK compuesta (id_sesion, id_usuario) apunta a la PK de sesion: la sesión debe existir y ser de la
 *   misma cuenta que firmó la operación. Con id_sesion nulo PostgreSQL no comprueba la FK.
 * - El índice parcial sirve para buscar «todo lo que se hizo desde la sesión X».
 * - Las filas anteriores quedan sin sesión.
 */
class AddIdSesionToLog extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
            ALTER TABLE public.log
                ADD COLUMN id_sesion integer,
                ADD CONSTRAINT fk_log_sesion
                    FOREIGN KEY (id_sesion, id_usuario) REFERENCES public.sesion(id_sesion, id_usuario)
        SQL);

        DB::statement(<<<'SQL'
            CREATE INDEX idx_log_sesion
                ON public.log (id_sesion)
                WHERE id_sesion IS NOT NULL
        SQL);
    }

    public function down()
    {
        DB::statement('DROP INDEX IF EXISTS public.idx_log_sesion');
        DB::statement('ALTER TABLE public.log DROP CONSTRAINT fk_log_sesion, DROP COLUMN id_sesion');
    }
}
