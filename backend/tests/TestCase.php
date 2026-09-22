<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * El esquema no lo definen migraciones sino el script de creación del proyecto,
     * que es la única fuente de verdad. Se carga una sola vez por corrida.
     */
    private static bool $schemaLoaded = false;

    protected function setUp(): void
    {
        /*
         * La carga ocurre antes de parent::setUp() y sobre una instancia aparte de la
         * aplicación: DatabaseTransactions abre su transacción en parent::setUp() y el
         * rollback de cada test se llevaría las tablas recién creadas.
         */
        if (! self::$schemaLoaded) {
            self::loadDatabaseSchema($this->createApplication());
            self::$schemaLoaded = true;
        }

        parent::setUp();
    }

    private static function loadDatabaseSchema(Application $app): void
    {
        $script = dirname($app->basePath()) . '/docs/database/creation-script.sql';

        if (! is_file($script)) {
            throw new RuntimeException("No se encontró el script de creación: {$script}");
        }

        $connection = $app->make('db')->connection();

        if ($connection->getDatabaseName() !== 'sciem_test') {
            throw new RuntimeException(
                'Las pruebas solo pueden reiniciar la base de datos sciem_test.'
            );
        }

        $connection->unprepared('DROP SCHEMA IF EXISTS public CASCADE; CREATE SCHEMA public;');
        $connection->unprepared(file_get_contents($script));
    }
}
