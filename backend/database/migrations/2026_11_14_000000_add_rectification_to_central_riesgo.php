<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rectificación de un registro de la Central de Riesgo de estudiantes (HU-062).
 *
 * Rectificar es anular el registro: un administrador lo retira de la Central cuando se concluye que no
 * correspondía. La fila nunca se borra ni se reactiva; solo se marca con tres columnas nuevas que deben
 * estar las tres vacías (registro activo) o las tres con valor (registro rectificado).
 *
 * reporte_estudiante, su evidencia y sus datos de revisión no se tocan.
 *
 * Solo se agregan objetos y no hay UPDATE masivo: la migración es idempotente y segura de repetir. Las
 * constraints se crean solo si faltan, porque ADD CONSTRAINT no admite IF NOT EXISTS.
 *
 * También registra la acción de bitácora RECTIFICAR_REGISTRO. accion.operacion no es UNIQUE, así que se
 * inserta solo si falta.
 *
 * down() aborta sin tocar nada si ya hay registros rectificados o filas de bitácora de la acción.
 */
class AddRectificationToCentralRiesgo extends Migration
{
    public function up()
    {
        DB::unprepared(<<<'SQL'
ALTER TABLE public.central_riesgo
    ADD COLUMN IF NOT EXISTS rectificado_en          timestamp with time zone,
    ADD COLUMN IF NOT EXISTS id_usuario_rectificador uuid,
    ADD COLUMN IF NOT EXISTS motivo_rectificacion    text;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'fk_central_riesgo_rectificador'
          AND conrelid = 'public.central_riesgo'::regclass
    ) THEN
        ALTER TABLE public.central_riesgo
            ADD CONSTRAINT fk_central_riesgo_rectificador
            FOREIGN KEY (id_usuario_rectificador) REFERENCES public.usuario(id_usuario);
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'chk_central_riesgo_rectificacion'
          AND conrelid = 'public.central_riesgo'::regclass
    ) THEN
        ALTER TABLE public.central_riesgo
            ADD CONSTRAINT chk_central_riesgo_rectificacion CHECK (
                (rectificado_en IS NULL
                    AND id_usuario_rectificador IS NULL
                    AND motivo_rectificacion IS NULL)
                OR
                (rectificado_en IS NOT NULL
                    AND id_usuario_rectificador IS NOT NULL
                    AND motivo_rectificacion IS NOT NULL
                    AND length(btrim(motivo_rectificacion)) > 0)
            );
    END IF;
END $$;

CREATE INDEX IF NOT EXISTS idx_central_riesgo_activo_estudiante
    ON public.central_riesgo (id_estudiante)
    WHERE rectificado_en IS NULL;

CREATE INDEX IF NOT EXISTS idx_central_riesgo_activo_fecha
    ON public.central_riesgo (fecha_registro DESC, id_cr_estudiante DESC)
    WHERE rectificado_en IS NULL;

INSERT INTO public.accion (operacion, tipo_operacion, descripcion)
SELECT 'RECTIFICAR_REGISTRO', 'UPDATE', 'Rectificación de un registro de la Central de Riesgo'
WHERE NOT EXISTS (SELECT 1 FROM public.accion WHERE operacion = 'RECTIFICAR_REGISTRO');
SQL);
    }

    public function down()
    {
        $voided = Schema::hasColumn('central_riesgo', 'rectificado_en')
            ? DB::table('central_riesgo')->whereNotNull('rectificado_en')->count()
            : 0;

        $logged = DB::table('log')
            ->join('accion', 'accion.id_accion', '=', 'log.id_accion')
            ->where('accion.operacion', 'RECTIFICAR_REGISTRO')
            ->count();

        if ($voided > 0 || $logged > 0) {
            throw new RuntimeException(sprintf(
                'No se revirtió la rectificación de la central de riesgo: hay %d registro(s) rectificado(s) y %d '
                . 'fila(s) de bitácora de RECTIFICAR_REGISTRO. Revertir perdería el historial de rectificaciones. '
                . 'No se modificó nada.',
                $voided,
                $logged
            ));
        }

        DB::unprepared(<<<'SQL'
DROP INDEX IF EXISTS public.idx_central_riesgo_activo_fecha;
DROP INDEX IF EXISTS public.idx_central_riesgo_activo_estudiante;

ALTER TABLE public.central_riesgo
    DROP CONSTRAINT IF EXISTS chk_central_riesgo_rectificacion,
    DROP CONSTRAINT IF EXISTS fk_central_riesgo_rectificador,
    DROP COLUMN IF EXISTS motivo_rectificacion,
    DROP COLUMN IF EXISTS id_usuario_rectificador,
    DROP COLUMN IF EXISTS rectificado_en;

DELETE FROM public.accion WHERE operacion = 'RECTIFICAR_REGISTRO';
SQL);
    }
}
