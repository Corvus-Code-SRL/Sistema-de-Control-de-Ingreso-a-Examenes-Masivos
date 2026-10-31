<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Migración de datos: elimina del catálogo `accion` las cuatro filas que se insertaron a mano en la
 * base compartida antes de que existiera ActionSeeder (Crear, Actualizar, Leer y Desactivar).
 *
 * Ningún código las usa: AuditLogService busca la acción por `operacion` exacta y todas las del
 * catálogo vigente van en MAYÚSCULAS (CREAR, MODIFICAR…), que esta migración no toca.
 *
 * - Las filas se buscan por el texto exacto de `operacion`, nunca por id.
 * - Si no existe ninguna (base nueva, sciem_test, un servidor nuevo, o una segunda ejecución) no hace nada.
 * - Si alguna fila de `log` referencia una de ellas, aborta sin borrar nada: no se reasigna la bitácora.
 * - No es un cambio de esquema, así que no se refleja en docs/database/creation-script.sql.
 */
class DeleteLegacyAccionRows extends Migration
{
    private const LEGACY_OPERATIONS = ['Crear', 'Actualizar', 'Leer', 'Desactivar'];

    public function up()
    {
        DB::transaction(function () {
            $legacyIds = DB::table('accion')
                ->whereIn('operacion', self::LEGACY_OPERATIONS)
                ->pluck('id_accion');

            if ($legacyIds->isEmpty()) {
                return;
            }

            $references = DB::table('log')
                ->join('accion', 'accion.id_accion', '=', 'log.id_accion')
                ->whereIn('log.id_accion', $legacyIds)
                ->groupBy('accion.operacion')
                ->orderBy('accion.operacion')
                ->selectRaw('accion.operacion as operacion, count(*) as filas')
                ->get();

            if ($references->isNotEmpty()) {
                $detail = $references
                    ->map(fn ($row) => sprintf('%s (%d filas de log)', $row->operacion, $row->filas))
                    ->implode(', ');

                throw new RuntimeException(
                    'No se eliminaron las acciones heredadas de accion: la bitácora (tabla log) todavía las '
                    . 'referencia: ' . $detail . '. No se borró nada. Revise esas filas con el equipo y '
                    . 'vuelva a ejecutar la migración.'
                );
            }

            DB::table('accion')->whereIn('id_accion', $legacyIds)->delete();
        });
    }

    /**
     * No hace nada a propósito: revertir sería volver a insertar cuatro filas que ningún código usa
     * y que nunca formaron parte del catálogo vigente.
     */
    public function down()
    {
    }
}
