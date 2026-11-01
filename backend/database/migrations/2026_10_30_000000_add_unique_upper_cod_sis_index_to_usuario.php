<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * HU-001: el código de acceso es único sin distinguir mayúsculas. La aplicación ya lo guarda en
 * mayúsculas (SisCode::normalize) y el login lo normaliza igual; este índice lo garantiza en la
 * base, aunque una fila se escriba por fuera de la aplicación.
 *
 * - Si dos cuentas ya coinciden al pasar a mayúsculas la migración se detiene con un mensaje y no
 *   cambia nada: hay que resolver esos duplicados a mano.
 * - Un código guardado en minúsculas no podría iniciar sesión (el login busca en mayúsculas), así
 *   que se pasa a mayúsculas antes de crear el índice.
 * - Es idempotente: IF NOT EXISTS, y la conversión solo toca filas que lo necesitan.
 *
 * El índice debe formar parte del script único de creación de la base:
 *   CREATE UNIQUE INDEX unq_usuario_cod_sis_upper ON public.usuario (upper(cod_sis));
 */
class AddUniqueUpperCodSisIndexToUsuario extends Migration
{
    private const INDEX = 'unq_usuario_cod_sis_upper';

    public function up()
    {
        $duplicates = DB::select(
            'SELECT upper(cod_sis) AS cod_sis, count(*) AS cuentas
             FROM public.usuario
             GROUP BY upper(cod_sis)
             HAVING count(*) > 1'
        );

        if ($duplicates !== []) {
            $codes = implode(', ', array_map(fn ($row) => $row->cod_sis, $duplicates));

            throw new RuntimeException(
                "Hay cuentas con el mismo código SIS al ignorar mayúsculas ({$codes}). "
                . 'Resuélvalas antes de crear el índice único.'
            );
        }

        DB::transaction(function () {
            DB::statement('UPDATE public.usuario SET cod_sis = upper(cod_sis) WHERE cod_sis <> upper(cod_sis)');
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS ' . self::INDEX . ' ON public.usuario (upper(cod_sis))');
        });
    }

    public function down()
    {
        DB::statement('DROP INDEX IF EXISTS public.' . self::INDEX);
    }
}
