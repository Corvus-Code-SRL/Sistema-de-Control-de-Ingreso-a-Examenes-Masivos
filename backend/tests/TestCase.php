<?php

namespace Tests;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable as UserContract;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Sanctum\Sanctum;
use RuntimeException;

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

    /**
     * Toda autenticación de las pruebas pasa por Sanctum::actingAs, como un token real: así
     * actingAs() y actAsUserId() no pueden quedar con usuarios distintos en guards distintos.
     */
    public function actingAs(UserContract $user, $guard = null)
    {
        Sanctum::actingAs($user);

        return $this;
    }

    /**
     * Autentica la petición como la cuenta indicada y devuelve la cuenta. Los roles son los que la
     * cuenta tenga en la base: la prueba debe dárselos.
     */
    protected function actAsUserId(string $userId): User
    {
        $user = User::findOrFail($userId);

        $this->actingAs($user);

        return $user;
    }

    /** Descarta la sesión: la siguiente petición llega sin token. */
    protected function actAsGuest(): void
    {
        $this->app['auth']->forgetGuards();
    }

    /** Autentica como un docente; la prueba lo creó con rol Docente (seedAcademicCatalog() lo hace). */
    protected function actAsTeacher(string $teacherId): User
    {
        return $this->actAsUserId($teacherId);
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
