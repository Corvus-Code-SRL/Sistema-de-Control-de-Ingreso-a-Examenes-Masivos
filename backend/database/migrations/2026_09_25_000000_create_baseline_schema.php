<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Migración base: reproduce el esquema final de SCIEM (tipos enum, 36 tablas, índices, restricciones y
 * un trigger) a partir de docs/database/creation-script.sql, con HU-21, HU-24, las tablas de auxiliares,
 * minutos_apertura y periodo.gestion ya incorporados.
 *
 * En bases donde el esquema ya existe (la base compartida) no se ejecuta: se marca como aplicada
 * siguiendo docs/database/migracion-base.md. Todo cambio estructural posterior va en una migración
 * nueva; esta y su archivo SQL no se editan.
 */
class CreateBaselineSchema extends Migration
{
    public function up()
    {
        DB::unprepared(file_get_contents(database_path('schema/baseline.sql')));
    }

    public function down()
    {
        throw new RuntimeException(
            'La migración base no es reversible: revertirla borraría todo el esquema y sus datos.'
        );
    }
}
