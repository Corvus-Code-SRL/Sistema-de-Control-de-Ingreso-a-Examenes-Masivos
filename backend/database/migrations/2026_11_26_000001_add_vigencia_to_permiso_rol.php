<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Vigencia de permisos por rol (RNF-01).
 *
 * Lo que alguien hizo con un permiso sigue valiendo aunque el rol lo pierda después; para poder
 * demostrarlo, permiso_rol guarda desde y hasta cuándo un rol tuvo cada permiso, como ya lo hace
 * usuario_rol con los roles de cada cuenta. Quitar un permiso cierra su fila (fecha_fin), nunca la
 * borra; a lo sumo una fila abierta por par permiso–rol.
 *
 * El rol con el que se hizo cada operación de la bitácora no se guarda: se calcula cruzando
 * log.fecha_hora con las fechas de usuario_rol (AuditLogQueryService).
 */
class AddVigenciaToPermisoRol extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE public.permiso_rol DROP CONSTRAINT pk_permiso_rol');

        DB::statement(<<<'SQL'
            ALTER TABLE public.permiso_rol
                ADD COLUMN fecha_inicio timestamptz DEFAULT CURRENT_TIMESTAMP NOT NULL,
                ADD COLUMN fecha_fin timestamptz,
                ADD CONSTRAINT pk_permiso_rol PRIMARY KEY (id_permiso, id_rol, fecha_inicio),
                ADD CONSTRAINT chk_permiso_rol_fechas CHECK (fecha_fin IS NULL OR fecha_fin >= fecha_inicio)
        SQL);

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX unq_permiso_rol_vigente
                ON public.permiso_rol (id_permiso, id_rol)
                WHERE fecha_fin IS NULL
        SQL);
    }

    /**
     * Reversible en estructura, no en datos: se borran las filas históricas de cada par permiso–rol
     * y se pierde su vigencia. Respaldar permiso_rol antes (docs/database/vigencia-roles-permisos.md).
     */
    public function down(): void
    {
        // Al volver, solo queda la fila vigente (o la última) de cada par: la clave vuelve a ser (permiso, rol).
        DB::statement(<<<'SQL'
            DELETE FROM public.permiso_rol pr
            USING public.permiso_rol newer
            WHERE newer.id_permiso = pr.id_permiso
              AND newer.id_rol = pr.id_rol
              AND newer.fecha_inicio > pr.fecha_inicio
        SQL);

        DB::statement('DROP INDEX IF EXISTS public.unq_permiso_rol_vigente');
        DB::statement(<<<'SQL'
            ALTER TABLE public.permiso_rol
                DROP CONSTRAINT IF EXISTS chk_permiso_rol_fechas,
                DROP CONSTRAINT pk_permiso_rol,
                DROP COLUMN fecha_fin,
                DROP COLUMN fecha_inicio,
                ADD CONSTRAINT pk_permiso_rol PRIMARY KEY (id_permiso, id_rol)
        SQL);
    }
}
