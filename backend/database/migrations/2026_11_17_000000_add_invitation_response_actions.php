<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Registra las acciones de bitácora para responder invitaciones a colaborar (HU-027): ACEPTAR_INVITACION y
 * RECHAZAR_INVITACION.
 *
 * No crea columnas: permite_agregar_grupos y fecha_sin_efecto ya las creó HU-026, y el rechazo no guarda
 * motivo. accion.operacion no es UNIQUE, así que cada acción se inserta solo si falta: es idempotente y segura
 * de repetir.
 *
 * down() aborta sin tocar nada si ya hay filas de bitácora de estas acciones.
 */
class AddInvitationResponseActions extends Migration
{
    private const ACTIONS = ['ACEPTAR_INVITACION', 'RECHAZAR_INVITACION'];

    public function up()
    {
        DB::unprepared(<<<'SQL'
INSERT INTO public.accion (operacion, tipo_operacion, descripcion)
SELECT 'ACEPTAR_INVITACION', 'UPDATE', 'Aceptación de una invitación a colaborar en un examen'
WHERE NOT EXISTS (SELECT 1 FROM public.accion WHERE operacion = 'ACEPTAR_INVITACION');

INSERT INTO public.accion (operacion, tipo_operacion, descripcion)
SELECT 'RECHAZAR_INVITACION', 'UPDATE', 'Rechazo de una invitación a colaborar en un examen'
WHERE NOT EXISTS (SELECT 1 FROM public.accion WHERE operacion = 'RECHAZAR_INVITACION');
SQL);
    }

    public function down()
    {
        $logged = DB::table('log')
            ->join('accion', 'accion.id_accion', '=', 'log.id_accion')
            ->whereIn('accion.operacion', self::ACTIONS)
            ->count();

        if ($logged > 0) {
            throw new \RuntimeException(sprintf(
                'No se quitaron las acciones de respuesta a invitaciones: hay %d fila(s) de bitácora que las usan. '
                . 'No se modificó nada.',
                $logged
            ));
        }

        DB::table('accion')->whereIn('operacion', self::ACTIONS)->delete();
    }
}
