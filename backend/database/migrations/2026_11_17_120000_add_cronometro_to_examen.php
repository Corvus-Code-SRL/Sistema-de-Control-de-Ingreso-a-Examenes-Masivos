<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('examen', function (Blueprint $table): void {
            $table->timestampTz('cronometro_iniciado_en')->nullable();
            $table->timestampTz('cronometro_vence_en')->nullable();
        });

        DB::statement(<<<'SQL'
ALTER TABLE examen ADD CONSTRAINT examen_cronometro_fechas_check CHECK (
    (cronometro_iniciado_en IS NULL AND cronometro_vence_en IS NULL)
    OR (cronometro_iniciado_en IS NOT NULL AND cronometro_vence_en IS NOT NULL
        AND cronometro_vence_en > cronometro_iniciado_en)
)
SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE examen DROP CONSTRAINT examen_cronometro_fechas_check');
        Schema::table('examen', function (Blueprint $table): void {
            $table->dropColumn(['cronometro_iniciado_en', 'cronometro_vence_en']);
        });
    }
};
