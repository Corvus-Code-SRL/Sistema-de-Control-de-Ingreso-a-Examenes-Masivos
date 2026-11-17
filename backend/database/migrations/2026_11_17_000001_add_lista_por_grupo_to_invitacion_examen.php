<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Opción «lista separada por grupo» de la invitación a colaborar (HU-026 la elige, HU-027 la aplica).
 *
 * - lista_por_grupo: si el docente que invita pide que, cuando el colaborador sume sus grupos, la lista del
 *   examen se muestre separada por grupo (con su docente) en el control de ingreso y durante el examen. En
 *   false (por defecto) la lista va junta, como hasta ahora. La nómina es la misma en los dos casos: solo
 *   cambia cómo se muestra.
 * - chk_invitacion_examen_lista_por_grupo: solo tiene sentido si la invitación permite agregar grupos.
 *
 * Solo agrega objetos: es idempotente y segura de repetir. ADD CONSTRAINT no admite IF NOT EXISTS, así que se
 * crea solo si falta. down() quita la columna y la restricción (se pierde la opción elegida).
 */
class AddListaPorGrupoToInvitacionExamen extends Migration
{
    public function up()
    {
        DB::unprepared(<<<'SQL'
ALTER TABLE public.invitacion_examen
    ADD COLUMN IF NOT EXISTS lista_por_grupo boolean NOT NULL DEFAULT false;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'chk_invitacion_examen_lista_por_grupo'
          AND conrelid = 'public.invitacion_examen'::regclass
    ) THEN
        ALTER TABLE public.invitacion_examen
            ADD CONSTRAINT chk_invitacion_examen_lista_por_grupo
            CHECK (NOT lista_por_grupo OR permite_agregar_grupos);
    END IF;
END $$;
SQL);
    }

    public function down()
    {
        DB::unprepared(<<<'SQL'
ALTER TABLE public.invitacion_examen DROP CONSTRAINT IF EXISTS chk_invitacion_examen_lista_por_grupo;
ALTER TABLE public.invitacion_examen DROP COLUMN IF EXISTS lista_por_grupo;
SQL);
    }
}
