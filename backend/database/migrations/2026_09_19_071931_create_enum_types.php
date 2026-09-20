<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class CreateEnumTypes extends Migration
{
    public function up()
    {
        DB::statement("CREATE EXTENSION IF NOT EXISTS pgcrypto");

        $this->crearEnumSiNoExiste('estado_registro', "'ACTIVO', 'INACTIVO'");
        $this->crearEnumSiNoExiste('estado_habilitacion', "'HABILITADO', 'CASO ESPECIAL', 'NO_HABILITADO'");
        $this->crearEnumSiNoExiste('estado_ingreso', "'INGRESO', 'CON_RETRASO', 'NO_INGRESO'");
        $this->crearEnumSiNoExiste('estado_invitacion', "'PENDIENTE', 'ACEPTADA', 'RECHAZADA'");
        $this->crearEnumSiNoExiste('estado_reporte', "'PENDIENTE', 'APROBADO', 'RECHAZADO'");
        $this->crearEnumSiNoExiste('categoria_examen', "'REGULAR', 'MESA', 'ADMISION'");
    }

    public function down()
    {
        DB::statement("DROP TYPE IF EXISTS public.categoria_examen CASCADE");
        DB::statement("DROP TYPE IF EXISTS public.estado_reporte CASCADE");
        DB::statement("DROP TYPE IF EXISTS public.estado_invitacion CASCADE");
        DB::statement("DROP TYPE IF EXISTS public.estado_ingreso CASCADE");
        DB::statement("DROP TYPE IF EXISTS public.estado_habilitacion CASCADE");
        DB::statement("DROP TYPE IF EXISTS public.estado_registro CASCADE");
    }

    /**
     * PostgreSQL no soporta "CREATE TYPE IF NOT EXISTS", así que se verifica
     * la existencia con un bloque DO antes de crear el tipo. Esto evita el
     * error "already exists" cuando migrate:fresh borra tablas pero no
     * borra tipos ENUM personalizados (RefreshDatabase de los tests).
     */
    private function crearEnumSiNoExiste(string $nombre, string $valores): void
    {
        DB::statement("
            DO \$\$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = '{$nombre}') THEN
                    CREATE TYPE public.{$nombre} AS ENUM ({$valores});
                END IF;
            END\$\$;
        ");
    }
}