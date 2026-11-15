<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * HU-023: un estudiante puede entrar a un grupo con la marca «Excepcional» y su motivo.
 *
 * Es aditiva: las inscripciones que ya existen quedan como no excepcionales y sin motivo.
 * La restricción exige un motivo con texto cuando la marca está puesta.
 */
class AddExceptionalToGrupoEstudiante extends Migration
{
    public function up()
    {
        DB::unprepared(<<<'SQL'
ALTER TABLE public.grupo_estudiante
    ADD COLUMN es_excepcional boolean NOT NULL DEFAULT false,
    ADD COLUMN motivo_excepcional varchar(255),
    ADD CONSTRAINT chk_grupo_estudiante_excepcional_motivo
        CHECK (
            NOT es_excepcional
            OR (motivo_excepcional IS NOT NULL AND btrim(motivo_excepcional) <> '')
        );
SQL);
    }

    public function down()
    {
        DB::unprepared(<<<'SQL'
ALTER TABLE public.grupo_estudiante
    DROP CONSTRAINT IF EXISTS chk_grupo_estudiante_excepcional_motivo,
    DROP COLUMN IF EXISTS motivo_excepcional,
    DROP COLUMN IF EXISTS es_excepcional;
SQL);
    }
}