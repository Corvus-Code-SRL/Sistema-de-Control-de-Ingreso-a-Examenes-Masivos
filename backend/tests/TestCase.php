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

    private const TEST_DATABASE = 'sciem_test';

    // «postgres» es el nombre del servicio de Docker del equipo.
    private const TEST_HOSTS = ['127.0.0.1', 'localhost', '::1', 'postgres'];

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

        self::assertTestDatabase(
            $connection->getDatabaseName(),
            $connection->getConfig('host')
        );

        $connection->unprepared('DROP SCHEMA IF EXISTS public CASCADE; CREATE SCHEMA public;');

        $kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);

        $kernel->call('migrate', ['--force' => true]);
        $kernel->call('db:seed', [
            '--class' => \Database\Seeders\ActionSeeder::class,
            '--force' => true,
        ]);
    }
    /**
     * La suite borra y recrea el esquema completo, así que solo corre contra una base llamada
     * sciem_test cuyo host sea local (127.0.0.1, localhost, ::1) o el servicio de Docker «postgres».
     * Cualquier otra combinación aborta antes de ejecutar nada; el mensaje nunca incluye credenciales.
     *
     * @param  mixed  $host
     */
    public static function assertTestDatabase(?string $database, $host): void
    {
        $validHost = is_string($host) && in_array(strtolower(trim($host)), self::TEST_HOSTS, true);

        if ($database === self::TEST_DATABASE && $validHost) {
            return;
        }

        throw new RuntimeException(sprintf(
            'Pruebas abortadas: la base «%s» en el host «%s» no es la de pruebas. Se exige DB_DATABASE=%s '
            . 'y DB_HOST en [%s]. No se modificó nada.',
            $database ?? '(sin nombre)',
            is_string($host) ? $host : '(sin host)',
            self::TEST_DATABASE,
            implode(', ', self::TEST_HOSTS)
        ));
    }
}
