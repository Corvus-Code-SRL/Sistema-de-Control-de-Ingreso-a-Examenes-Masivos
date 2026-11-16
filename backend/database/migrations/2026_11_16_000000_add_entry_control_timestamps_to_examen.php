<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddEntryControlTimestampsToExamen extends Migration
{
    public function up()
    {
        Schema::table('examen', function (Blueprint $table): void {
            $table->timestampTz('ingreso_abierto_en')->nullable();
            $table->timestampTz('ingreso_ordinario_cerrado_en')->nullable();
        });

        DB::statement(<<<'SQL'
UPDATE public.examen AS e
SET ingreso_abierto_en = aperturas.fecha_hora
FROM (
    SELECT nuevo_valor->>'id_examen' AS id_examen, MIN(fecha_hora) AS fecha_hora
    FROM public.log
    WHERE tabla_afectada = 'examen' AND nuevo_valor->>'evento' = 'APERTURA_CONTROL'
    GROUP BY nuevo_valor->>'id_examen'
) AS aperturas
WHERE aperturas.id_examen = e.id_examen::text
SQL);

        DB::statement(<<<'SQL'
UPDATE public.examen AS e
SET ingreso_ordinario_cerrado_en = cierres.fecha_hora
FROM (
    SELECT nuevo_valor->>'id_examen' AS id_examen, MIN(fecha_hora) AS fecha_hora
    FROM public.log
    WHERE tabla_afectada = 'examen' AND nuevo_valor->>'evento' = 'CIERRE_INGRESO_ORDINARIO'
    GROUP BY nuevo_valor->>'id_examen'
) AS cierres
WHERE cierres.id_examen = e.id_examen::text
SQL);
    }

    public function down()
    {
        Schema::table('examen', function (Blueprint $table): void {
            $table->dropColumn(['ingreso_abierto_en', 'ingreso_ordinario_cerrado_en']);
        });
    }
}
