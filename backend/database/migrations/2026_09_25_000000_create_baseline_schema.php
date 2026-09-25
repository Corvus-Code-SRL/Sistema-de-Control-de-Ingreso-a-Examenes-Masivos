<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Migración base: reproduce el esquema vigente de SCIEM (tipos enum, 34 tablas, índices y
 * restricciones) a partir de una copia congelada de docs/database/creation-script.sql, ya con
 * los cambios de HU-21 y HU-24 incorporados.
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
