<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Retira el estado EN_INGRESO del examen (HU-038).
 *
 * El control de ingreso ya no es un estado: se abre con examen.ingreso_abierto_en y se cierra con
 * examen.ingreso_ordinario_cerrado_en. El valor sigue en el enum public.estado_examen, pero un CHECK
 * impide asignarlo.
 *
 * Es idempotente: si el CHECK ya existe, no hace nada.
 */
class RetireEstadoEnIngresoExamen extends Migration
{
    private const CHECK = 'chk_examen_estado_sin_en_ingreso';

    public function up()
    {
        Schema::table('examen', function (Blueprint $table): void {
            if (! Schema::hasColumn('examen', 'ingreso_abierto_en')) {
                $table->timestampTz('ingreso_abierto_en')->nullable();
            }

            if (! Schema::hasColumn('examen', 'ingreso_ordinario_cerrado_en')) {
                $table->timestampTz('ingreso_ordinario_cerrado_en')->nullable();
            }
        });

        if ($this->checkExists()) {
            return;
        }

        $zone = config('sciem.zona_horaria');

        // Primero los EN_CURSO y FINALIZADO de antes: en develop iniciar el examen cerraba el control,
        // así que su ingreso ordinario no debe reabrirse al desplegar. Los EN_INGRESO se convierten
        // después, para que conserven su ingreso abierto.
        DB::statement(<<<'SQL'
UPDATE public.examen AS e
SET ingreso_abierto_en = COALESCE(
        e.ingreso_abierto_en,
        e.inicio_real_en,
        (e.fecha + e.hora_inicio) AT TIME ZONE ?::text
    ),
    ingreso_ordinario_cerrado_en = COALESCE(
        LEAST(e.inicio_real_en, CASE WHEN e.estado = 'FINALIZADO' THEN e.fin_real_en END),
        e.ingreso_abierto_en,
        (e.fecha + e.hora_inicio) AT TIME ZONE ?::text
    )
WHERE e.ingreso_ordinario_cerrado_en IS NULL
  AND (
      e.estado = 'EN_CURSO'
      OR (e.estado = 'FINALIZADO' AND (e.ingreso_abierto_en IS NOT NULL OR e.inicio_real_en IS NOT NULL))
  )
SQL, [$zone, $zone]);

        DB::statement(<<<'SQL'
UPDATE public.examen AS e
SET ingreso_abierto_en = COALESCE(
        e.ingreso_abierto_en,
        (
            SELECT MIN(l.fecha_hora)
            FROM public.log AS l
            WHERE l.tabla_afectada = 'examen'
              AND l.nuevo_valor->>'id_examen' = e.id_examen::text
              AND l.nuevo_valor->>'evento' = 'APERTURA_CONTROL'
        ),
        (e.fecha + e.hora_inicio - make_interval(mins => e.minutos_apertura)) AT TIME ZONE ?::text
    ),
    estado = (
        CASE
            WHEN now() < (e.fecha + e.hora_inicio) AT TIME ZONE ?::text THEN 'PROGRAMADO'
            ELSE 'EN_CURSO'
        END
    )::public.estado_examen
WHERE e.estado = 'EN_INGRESO'
SQL, [$zone, $zone]);

        DB::statement(
            'ALTER TABLE public.examen ADD CONSTRAINT ' . self::CHECK . " CHECK (estado <> 'EN_INGRESO')"
        );
    }

    /**
     * Solo quita el CHECK. No puede restituir qué exámenes estaban EN_INGRESO (ya son PROGRAMADO o
     * EN_CURSO) ni distinguir las fechas que la migración rellenó de las reales: esas columnas se
     * conservan, y las elimina la migración 2026_11_16_000000 si también se revierte.
     * Volver a ejecutar up() después de usar el modelo nuevo cerraría el ingreso de los EN_CURSO abiertos.
     */
    public function down()
    {
        DB::statement('ALTER TABLE public.examen DROP CONSTRAINT IF EXISTS ' . self::CHECK);
    }

    private function checkExists(): bool
    {
        return DB::table('pg_constraint')
            ->where('conname', self::CHECK)
            ->where('conrelid', DB::raw("'public.examen'::regclass"))
            ->exists();
    }
}
