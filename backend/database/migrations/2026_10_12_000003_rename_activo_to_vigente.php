<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * gestion_periodo.activo pasa a llamarse `vigente`, y los períodos numéricos del catálogo reciben su
 * nombre académico.
 *
 * `estado` dice si el registro está habilitado (ACTIVO/INACTIVO, igual que en toda tabla); `vigente`
 * dice cuál es el único período en curso. Con «activo» ambos conceptos se confundían.
 *
 *   - La columna y su índice parcial se renombran; los valores se conservan (el período vigente que
 *     ya está fijado, p. ej. el id 2 de la base compartida, sigue siéndolo).
 *   - periodo.nombre: 1 → «Semestre 1», 2 → «Semestre 2». Anual, Verano e Invierno no cambian. Se
 *     actualiza por `codigo`, nunca por id.
 *
 * No toca gestion_periodo (más allá del nombre de la columna) ni oferta_academica, y no depende de
 * SCIEM_PERIODO_ACTIVO_ID. Es idempotente: cada paso comprueba antes si ya se hizo.
 */
class RenameActivoToVigente extends Migration
{
    public function up()
    {
        DB::unprepared(<<<'SQL'
DO $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'public' AND table_name = 'gestion_periodo' AND column_name = 'activo'
    ) AND NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'public' AND table_name = 'gestion_periodo' AND column_name = 'vigente'
    ) THEN
        ALTER TABLE public.gestion_periodo RENAME COLUMN activo TO vigente;
    END IF;

    IF to_regclass('public.unq_gestion_periodo_activo') IS NOT NULL
       AND to_regclass('public.unq_gestion_periodo_vigente') IS NULL THEN
        ALTER INDEX public.unq_gestion_periodo_activo RENAME TO unq_gestion_periodo_vigente;
    END IF;
END
$$;

UPDATE public.periodo SET nombre = 'Semestre 1' WHERE codigo = 1 AND nombre <> 'Semestre 1';
UPDATE public.periodo SET nombre = 'Semestre 2' WHERE codigo = 2 AND nombre <> 'Semestre 2';
SQL);
    }

    public function down()
    {
        DB::unprepared(<<<'SQL'
DO $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'public' AND table_name = 'gestion_periodo' AND column_name = 'vigente'
    ) AND NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'public' AND table_name = 'gestion_periodo' AND column_name = 'activo'
    ) THEN
        ALTER TABLE public.gestion_periodo RENAME COLUMN vigente TO activo;
    END IF;

    IF to_regclass('public.unq_gestion_periodo_vigente') IS NOT NULL
       AND to_regclass('public.unq_gestion_periodo_activo') IS NULL THEN
        ALTER INDEX public.unq_gestion_periodo_vigente RENAME TO unq_gestion_periodo_activo;
    END IF;
END
$$;

UPDATE public.periodo SET nombre = '1' WHERE codigo = 1 AND nombre <> '1';
UPDATE public.periodo SET nombre = '2' WHERE codigo = 2 AND nombre <> '2';
SQL);
    }
}
