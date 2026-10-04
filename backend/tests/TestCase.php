<?php

namespace Tests;

use App\Support\CurrentUser;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;
use Tests\Support\FakeCurrentUser;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /** El esquema de pruebas se reconstruye desde la migración base y las posteriores. */
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

    /** Fija el docente que actúa en Academic y Exams, sin tocar la configuración. */
    protected function actAsTeacher(string $teacherId): void
    {
        $this->app->instance(CurrentUser::class, $this->fakeCurrentUser()->withTeacherId($teacherId));
    }

    /** Fija la cuenta que actúa en Security y la bitácora. */
    protected function actAsUserId(string $userId): void
    {
        $this->app->instance(CurrentUser::class, $this->fakeCurrentUser()->withId($userId));
    }

    private function fakeCurrentUser(): FakeCurrentUser
    {
        $current = $this->app->make(CurrentUser::class);

        return $current instanceof FakeCurrentUser ? $current : new FakeCurrentUser();
    }

    private static function loadDatabaseSchema(Application $app): void
    {
        $connection = $app->make('db')->connection();

        if ($connection->getDatabaseName() !== 'sciem_test') {
            throw new RuntimeException(
                'Las pruebas solo pueden reiniciar la base de datos sciem_test.'
            );
        }

        $connection->unprepared('DROP SCHEMA IF EXISTS public CASCADE; CREATE SCHEMA public;');
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->call('migrate', ['--force' => true]);
    }
}
