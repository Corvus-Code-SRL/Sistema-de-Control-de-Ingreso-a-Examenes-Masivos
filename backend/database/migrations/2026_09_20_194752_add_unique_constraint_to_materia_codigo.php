<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddUniqueConstraintToMateriaCodigo extends Migration
{
    public function up(): void
    {
        DB::statement("
            DO \$\$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM pg_constraint WHERE conname = 'unq_materia_codigo'
                ) THEN
                    ALTER TABLE public.materia ADD CONSTRAINT unq_materia_codigo UNIQUE (codigo);
                END IF;
            END\$\$;
        ");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE public.materia DROP CONSTRAINT IF EXISTS unq_materia_codigo");
    }
}