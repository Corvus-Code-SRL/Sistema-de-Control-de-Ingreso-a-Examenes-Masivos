<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Prepara invitacion_examen para invitar docentes colaboradores (HU-026).
 *
 * - permite_agregar_grupos: si el colaborador, al aceptar, puede sumar sus propios grupos de la materia al
 *   examen. HU-026 solo lo guarda; HU-027 lo hace cumplir.
 * - fecha_sin_efecto: cuándo la invitación quedó sin efecto (el examen salió de PROGRAMADO sin respuesta).
 * - chk_invitacion_examen_estado se reemplaza para admitir SIN_EFECTO (requiere la migración anterior).
 * - Un docente no se invita a sí mismo, y no puede tener dos invitaciones vigentes (PENDIENTE o ACEPTADA)
 *   al mismo examen; tras RECHAZADA o SIN_EFECTO se lo puede invitar de nuevo.
 * - Registra la acción de bitácora INVITAR_COLABORADOR (accion.operacion no es UNIQUE: se inserta si falta).
 *
 * Solo agrega objetos y no hace UPDATE masivo: es idempotente y segura de repetir. ADD CONSTRAINT no admite
 * IF NOT EXISTS, así que cada constraint se crea solo si falta.
 *
 * down() aborta sin tocar nada si ya hay invitaciones o filas de bitácora de la acción.
 */
class PrepareInvitacionExamenForCollaborators extends Migration
{
    public function up()
    {
        DB::unprepared(<<<'SQL'
ALTER TABLE public.invitacion_examen
    ADD COLUMN IF NOT EXISTS permite_agregar_grupos boolean NOT NULL DEFAULT true,
    ADD COLUMN IF NOT EXISTS fecha_sin_efecto       timestamp with time zone;

DO $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'chk_invitacion_examen_estado'
          AND conrelid = 'public.invitacion_examen'::regclass
          AND pg_get_constraintdef(oid) NOT LIKE '%SIN_EFECTO%'
    ) THEN
        ALTER TABLE public.invitacion_examen DROP CONSTRAINT chk_invitacion_examen_estado;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'chk_invitacion_examen_estado'
          AND conrelid = 'public.invitacion_examen'::regclass
    ) THEN
        ALTER TABLE public.invitacion_examen
            ADD CONSTRAINT chk_invitacion_examen_estado CHECK (
                (estado = 'PENDIENTE'
                    AND fecha_respuesta IS NULL AND fecha_sin_efecto IS NULL)
                OR
                (estado IN ('ACEPTADA', 'RECHAZADA')
                    AND fecha_respuesta IS NOT NULL AND fecha_sin_efecto IS NULL)
                OR
                (estado = 'SIN_EFECTO'
                    AND fecha_respuesta IS NULL AND fecha_sin_efecto IS NOT NULL)
            );
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'chk_invitacion_examen_no_autoinvitacion'
          AND conrelid = 'public.invitacion_examen'::regclass
    ) THEN
        ALTER TABLE public.invitacion_examen
            ADD CONSTRAINT chk_invitacion_examen_no_autoinvitacion
            CHECK (id_docente_creador <> id_docente_invitado);
    END IF;
END $$;

CREATE UNIQUE INDEX IF NOT EXISTS uq_invitacion_examen_vigente
    ON public.invitacion_examen (id_examen, id_docente_invitado)
    WHERE estado IN ('PENDIENTE', 'ACEPTADA');

INSERT INTO public.accion (operacion, tipo_operacion, descripcion)
SELECT 'INVITAR_COLABORADOR', 'INSERT', 'Invitación de un docente colaborador a un examen'
WHERE NOT EXISTS (SELECT 1 FROM public.accion WHERE operacion = 'INVITAR_COLABORADOR');
SQL);
    }

    public function down()
    {
        $invitations = Schema::hasColumn('invitacion_examen', 'permite_agregar_grupos')
            ? DB::table('invitacion_examen')->count()
            : 0;

        $logged = DB::table('log')
            ->join('accion', 'accion.id_accion', '=', 'log.id_accion')
            ->where('accion.operacion', 'INVITAR_COLABORADOR')
            ->count();

        if ($invitations > 0 || $logged > 0) {
            throw new RuntimeException(sprintf(
                'No se revirtió la preparación de las invitaciones: hay %d invitación(es) y %d fila(s) de '
                . 'bitácora de INVITAR_COLABORADOR. Revertir perdería el historial. No se modificó nada.',
                $invitations,
                $logged
            ));
        }

        DB::unprepared(<<<'SQL'
DROP INDEX IF EXISTS public.uq_invitacion_examen_vigente;

ALTER TABLE public.invitacion_examen
    DROP CONSTRAINT IF EXISTS chk_invitacion_examen_no_autoinvitacion,
    DROP CONSTRAINT IF EXISTS chk_invitacion_examen_estado;

ALTER TABLE public.invitacion_examen
    ADD CONSTRAINT chk_invitacion_examen_estado CHECK (
        (estado = 'PENDIENTE' AND fecha_respuesta IS NULL)
        OR
        (estado IN ('ACEPTADA', 'RECHAZADA') AND fecha_respuesta IS NOT NULL)
    );

ALTER TABLE public.invitacion_examen
    DROP COLUMN IF EXISTS fecha_sin_efecto,
    DROP COLUMN IF EXISTS permite_agregar_grupos;

DELETE FROM public.accion WHERE operacion = 'INVITAR_COLABORADOR';
SQL);
    }
}
