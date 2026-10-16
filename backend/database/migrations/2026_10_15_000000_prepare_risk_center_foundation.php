<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Base de la Central de Riesgo de estudiantes (HU-042, HU-045 y HU-046).
 *
 * reporte_estudiante pasa a poder nacer de un intento de ingreso rechazado y a admitir reportes
 * sin evidencia:
 *
 *   - `id_intento` (nullable, UNIQUE, FK a intento_ingreso): un intento origina como máximo un reporte.
 *   - `evidencia_url` y `evidencia_public_id` dejan de ser NOT NULL.
 *   - chk_reporte_estudiante_evidencia: o hay evidencia completa o no hay ninguna de las dos.
 *
 * La coherencia reporte ↔ intento ↔ examen ↔ estudiante no se impone en la base: la valida el
 * servicio de HU-042.
 *
 * Cada paso comprueba antes si ya se hizo, así que reintentar la migración no falla. Todo corre en la
 * transacción de la migración (el DDL de PostgreSQL es transaccional).
 *
 * down() aborta sin tocar nada si revertir perdería datos: una fila sin evidencia no admite
 * NOT NULL, y borrar `id_intento` perdería el vínculo de los reportes que ya lo tienen.
 */
class PrepareRiskCenterFoundation extends Migration
{
    public function up()
    {
        DB::unprepared(<<<'SQL'
ALTER TABLE public.reporte_estudiante ADD COLUMN IF NOT EXISTS id_intento bigint;

ALTER TABLE public.reporte_estudiante
    ALTER COLUMN evidencia_url DROP NOT NULL,
    ALTER COLUMN evidencia_public_id DROP NOT NULL;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conrelid = 'public.reporte_estudiante'::regclass AND conname = 'unq_reporte_estudiante_id_intento'
    ) THEN
        ALTER TABLE public.reporte_estudiante
            ADD CONSTRAINT unq_reporte_estudiante_id_intento UNIQUE (id_intento);
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conrelid = 'public.reporte_estudiante'::regclass AND conname = 'fk_reporte_estudiante_intento'
    ) THEN
        ALTER TABLE public.reporte_estudiante
            ADD CONSTRAINT fk_reporte_estudiante_intento
                FOREIGN KEY (id_intento) REFERENCES public.intento_ingreso(id_intento);
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conrelid = 'public.reporte_estudiante'::regclass AND conname = 'chk_reporte_estudiante_evidencia'
    ) THEN
        ALTER TABLE public.reporte_estudiante
            ADD CONSTRAINT chk_reporte_estudiante_evidencia
                CHECK ((evidencia_url IS NULL) = (evidencia_public_id IS NULL));
    END IF;
END
$$;
SQL);
    }

    public function down()
    {
        $withoutEvidence = DB::table('reporte_estudiante')
            ->where(fn ($query) => $query->whereNull('evidencia_url')->orWhereNull('evidencia_public_id'))
            ->count();

        $linked = Schema::hasColumn('reporte_estudiante', 'id_intento')
            ? DB::table('reporte_estudiante')->whereNotNull('id_intento')->count()
            : 0;

        if ($withoutEvidence > 0 || $linked > 0) {
            throw new RuntimeException(sprintf(
                'No se revirtió la base de la central de riesgo: reporte_estudiante tiene %d fila(s) sin '
                . 'evidencia (no admiten NOT NULL) y %d fila(s) con id_intento (se perdería el vínculo con el '
                . 'intento de ingreso). No se modificó nada. Resuelva esas filas con el equipo y vuelva a '
                . 'ejecutar el rollback.',
                $withoutEvidence,
                $linked
            ));
        }

        DB::unprepared(<<<'SQL'
ALTER TABLE public.reporte_estudiante
    DROP CONSTRAINT IF EXISTS chk_reporte_estudiante_evidencia,
    DROP CONSTRAINT IF EXISTS fk_reporte_estudiante_intento,
    DROP CONSTRAINT IF EXISTS unq_reporte_estudiante_id_intento;

ALTER TABLE public.reporte_estudiante
    ALTER COLUMN evidencia_url SET NOT NULL,
    ALTER COLUMN evidencia_public_id SET NOT NULL;

ALTER TABLE public.reporte_estudiante DROP COLUMN IF EXISTS id_intento;
SQL);
    }
}
