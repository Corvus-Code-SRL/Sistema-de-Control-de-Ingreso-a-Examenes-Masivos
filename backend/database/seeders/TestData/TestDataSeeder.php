<?php

namespace Database\Seeders\TestData;

use Database\Seeders\ActionSeeder;
use Database\Seeders\ExamTypeSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Datos de prueba compartidos por el equipo: TEMPORALES, se reemplazarán por datos reales.
 *
 * Deterministas (todo valor escrito a mano, sin Faker) e idempotentes (upsert por clave
 * primaria). No forman parte de DatabaseSeeder; se cargan a mano con:
 *
 *     php artisan db:seed --class="Database\Seeders\TestData\TestDataSeeder"
 *
 * Todo corre en una transacción: si algo falla, la base queda como estaba.
 */
class TestDataSeeder extends Seeder
{
    private const ALLOWED_ENVIRONMENTS = ['local', 'development'];

    public function run()
    {
        // Nunca en producción ni en la suite de pruebas (APP_ENV=testing).
        if (! app()->environment(self::ALLOWED_ENVIRONMENTS)) {
            throw new RuntimeException(sprintf(
                'Los datos de prueba solo se cargan con APP_ENV local o development; el entorno actual es «%s».',
                app()->environment()
            ));
        }

        if (DB::connection()->getDriverName() !== 'pgsql') {
            throw new RuntimeException(
                'Los datos compartidos requieren PostgreSQL y el esquema de creation-script.sql.'
            );
        }

        $database = DB::selectOne('select current_database() as name')->name;

        if (strtolower($database) === 'sciem_test') {
            throw new RuntimeException(
                'No se permite cargar datos compartidos en sciem_test, incluso con APP_ENV=local.'
            );
        }

        if (! Schema::hasColumns('examen', ['id_carrera', 'id_materia', 'id_usuario_docente', 'estado'])) {
            throw new RuntimeException(
                'Falta el esquema de HU-024: use creation-script.sql en una base nueva '
                . 'o revise docs/database/alter-hu-24-examen.sql para actualizar una existente.'
            );
        }

        try {
            $this->seedInTransaction();
        } catch (QueryException $exception) {
            // 23505: una clave única (código, nombre de periodo, cod_sis...) ya existe con otro id.
            if ($exception->getCode() !== '23505') {
                throw $exception;
            }

            throw new RuntimeException(
                'La base ya tiene registros que chocan con los datos de prueba (misma clave única, otro id). '
                . 'Cárguelos sobre una base recreada desde docs/database/creation-script.sql. '
                . 'No se escribió nada. Detalle: ' . ($exception->errorInfo[2] ?? $exception->getMessage()),
                0,
                $exception
            );
        }
    }

    private function seedInTransaction(): void
    {
        DB::transaction(function () {
            // Catálogos base del sistema, idempotentes por clave natural.
            $this->call([
                RoleSeeder::class,
                ActionSeeder::class,
                ExamTypeSeeder::class,
            ]);

            $this->call([
                CatalogTestDataSeeder::class,
                AccountTestDataSeeder::class,
                GroupTestDataSeeder::class,
                ExamTestDataSeeder::class,
            ]);
        });
    }
}
