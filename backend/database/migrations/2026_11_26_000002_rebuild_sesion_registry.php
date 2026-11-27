<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Registro de sesiones: una fila de `sesion` por cada inicio de sesión (un token de Sanctum).
 *
 * - Se quitan `pid` y `num_puerto`: eran del proceso de PostgreSQL, no de la sesión de la persona.
 * - `id_token` une la sesión con su token; al borrar el token queda nulo y la fila se conserva.
 * - `agente` guarda el navegador o dispositivo (User-Agent), recortado a 255 caracteres.
 * - `hora_salida` y `motivo_salida` se llenan juntos al cerrar: CIERRE (la persona salió), EXPIRADA
 *   (venció el token) o REVOCADA (cambio de contraseña o cuenta deshabilitada).
 * - La PK compuesta (id_sesion, id_usuario) se conserva.
 *
 * Nada escribía en `sesion` antes de esta migración. Si hubiera filas, se marcan como EXPIRADA.
 */
class RebuildSesionRegistry extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
            ALTER TABLE public.sesion
                DROP COLUMN pid,
                DROP COLUMN num_puerto,
                ADD COLUMN id_token bigint,
                ADD COLUMN agente varchar(255),
                ADD COLUMN hora_salida timestamptz,
                ADD COLUMN motivo_salida varchar(10)
        SQL);

        DB::statement("UPDATE public.sesion SET hora_salida = hora_ingreso, motivo_salida = 'EXPIRADA'");

        DB::statement(<<<'SQL'
            ALTER TABLE public.sesion
                ADD CONSTRAINT unq_sesion_token UNIQUE (id_token),
                ADD CONSTRAINT fk_sesion_token
                    FOREIGN KEY (id_token) REFERENCES public.personal_access_tokens(id) ON DELETE SET NULL,
                ADD CONSTRAINT chk_sesion_motivo_salida
                    CHECK (motivo_salida IN ('CIERRE', 'EXPIRADA', 'REVOCADA')),
                ADD CONSTRAINT chk_sesion_salida_completa
                    CHECK ((hora_salida IS NULL) = (motivo_salida IS NULL)),
                ADD CONSTRAINT chk_sesion_horas
                    CHECK (hora_salida IS NULL OR hora_salida >= hora_ingreso)
        SQL);

        DB::statement(<<<'SQL'
            CREATE INDEX idx_sesion_abierta
                ON public.sesion (id_usuario)
                WHERE hora_salida IS NULL
        SQL);
    }

    public function down()
    {
        DB::statement('DROP INDEX IF EXISTS public.idx_sesion_abierta');

        DB::statement(<<<'SQL'
            ALTER TABLE public.sesion
                DROP CONSTRAINT chk_sesion_horas,
                DROP CONSTRAINT chk_sesion_salida_completa,
                DROP CONSTRAINT chk_sesion_motivo_salida,
                DROP CONSTRAINT fk_sesion_token,
                DROP CONSTRAINT unq_sesion_token,
                DROP COLUMN motivo_salida,
                DROP COLUMN hora_salida,
                DROP COLUMN agente,
                DROP COLUMN id_token,
                ADD COLUMN pid integer NOT NULL DEFAULT 0,
                ADD COLUMN num_puerto integer NOT NULL DEFAULT 0
        SQL);

        DB::statement('ALTER TABLE public.sesion ALTER COLUMN pid DROP DEFAULT, ALTER COLUMN num_puerto DROP DEFAULT');
    }
}
