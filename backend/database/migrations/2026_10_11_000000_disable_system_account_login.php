<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Migración de datos (RNF-02): la cuenta de sistema sembrada por UserSeeder quedó con SIS `000000000`
 * y contraseña `password`, es decir, una puerta de entrada conocida como Administrador. Esta migración
 * la cierra en las bases que ya la tienen.
 *
 * - La fila se busca únicamente por cod_sis = '000000000', nunca por id_usuario: el id de la cuenta
 *   de sistema coincide con el del Administrador de los datos de prueba, que no debe tocarse.
 * - La fila NO se borra ni se desactiva: la bitácora (log.id_usuario) la referencia. Solo cambia la
 *   contraseña por un hash bcrypt de un secreto que nadie conoce, y se revocan sus tokens (duran 12 h).
 * - Si no existe ninguna cuenta con ese cod_sis, o ya tiene el hash inutilizable, no hace nada.
 * - No es un cambio de esquema, así que no se refleja en docs/database/creation-script.sql.
 */
class DisableSystemAccountLogin extends Migration
{
    private const SYSTEM_SIS_CODE = '000000000';

    /** Mismo valor que Database\Seeders\UserSeeder::UNUSABLE_PASSWORD_HASH, copiado a propósito. */
    private const UNUSABLE_PASSWORD_HASH = '$2y$10$gYra3xR5Z6gTh.3jZwPvB.XsimLMUuP92p4B5oiJ.Lqs5F59vDL3m';

    public function up()
    {
        DB::transaction(function () {
            $accountIds = DB::table('usuario')
                ->where('cod_sis', self::SYSTEM_SIS_CODE)
                ->pluck('id_usuario');

            if ($accountIds->isEmpty()) {
                return;
            }

            DB::table('usuario')
                ->whereIn('id_usuario', $accountIds)
                ->whereRaw('contrasenia IS DISTINCT FROM ?', [self::UNUSABLE_PASSWORD_HASH])
                ->update(['contrasenia' => self::UNUSABLE_PASSWORD_HASH]);

            DB::table('personal_access_tokens')->whereIn('tokenable_id', $accountIds)->delete();
        });
    }

    /**
     * No hace nada a propósito: restaurar la contraseña `password` reabriría la puerta de entrada.
     */
    public function down()
    {
    }
}
