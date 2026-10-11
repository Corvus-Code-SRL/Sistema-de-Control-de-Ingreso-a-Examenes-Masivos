<?php

namespace Tests\Feature\Exceptions;

use App\Exceptions\Academic\ActivePeriodNotConfiguredException;
use App\Exceptions\Handler;
use App\Exceptions\Security\DuplicateAccountException;
use App\Exceptions\Security\SisNoDisponibleException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use ReflectionClass;
use RuntimeException;
use Tests\TestCase;
use TypeError;

/**
 * Las excepciones de negocio son resultados esperados y no se reportan al log; los errores reales
 * (QueryException, TypeError, un RuntimeException cualquiera) sí. Evita que una suite o un día de
 * producción llenen el log de trazas —algunas con nombres de personas— y tapen los fallos de verdad.
 */
class ErrorReportingTest extends TestCase
{
    /**
     * Las únicas clases de app/Exceptions que se reportan a propósito: un error de despliegue y el
     * SIS caído (señal operativa). Cualquier otra excepción nueva debe ir en Handler::$dontReport.
     */
    private const REPORTED = [
        ActivePeriodNotConfiguredException::class,
        SisNoDisponibleException::class,
    ];

    public function test_toda_excepcion_de_app_exceptions_figura_en_dont_report_o_en_la_lista_de_reportadas(): void
    {
        $dontReport = $this->dontReport();
        $unclassified = [];

        foreach ($this->exceptionClasses() as $class) {
            if (! in_array($class, $dontReport, true) && ! in_array($class, self::REPORTED, true)) {
                $unclassified[] = $class;
            }
        }

        $this->assertSame(
            [],
            $unclassified,
            "Agréguelas a Handler::\$dontReport (negocio) o a ErrorReportingTest::REPORTED (error real):\n"
            . implode("\n", $unclassified)
        );
    }

    public function test_las_excepciones_reportadas_a_proposito_no_estan_en_dont_report(): void
    {
        foreach (self::REPORTED as $class) {
            $this->assertNotContains($class, $this->dontReport(), "{$class} debe seguir reportándose.");
        }
    }

    public function test_dont_report_solo_lista_clases_que_existen_en_app_exceptions(): void
    {
        $known = $this->exceptionClasses();

        foreach ($this->dontReport() as $class) {
            $this->assertContains($class, $known, "{$class} no es una clase de app/Exceptions.");
        }
    }

    public function test_una_excepcion_de_negocio_no_escribe_traza_en_el_log(): void
    {
        $log = Log::spy();
        $handler = $this->app->make(ExceptionHandler::class);

        foreach ($this->exceptionClasses() as $class) {
            if (in_array($class, self::REPORTED, true)) {
                continue;
            }

            $handler->report($this->makeException($class));
        }

        $log->shouldNotHaveReceived('error');
    }

    public function test_una_excepcion_real_sigue_escribiendo_traza_en_el_log(): void
    {
        $log = Log::spy();
        $handler = $this->app->make(ExceptionHandler::class);

        $handler->report(new RuntimeException('falla real'));
        $handler->report(new TypeError('tipo incorrecto'));
        $handler->report(new QueryException('select 1', [], new RuntimeException('conexión perdida')));
        $handler->report(new SisNoDisponibleException());
        $handler->report(new ActivePeriodNotConfiguredException());

        $log->shouldHaveReceived('error')->times(5);
    }

    public function test_un_login_rechazado_por_la_api_no_escribe_traza_en_el_log(): void
    {
        $log = Log::spy();

        $this->postJson('/api/auth/login', ['cod_sis' => '99999', 'password' => 'incorrecta'])
            ->assertStatus(401)
            ->assertJsonPath('motivo', 'credenciales_invalidas');

        $log->shouldNotHaveReceived('error');
    }

    public function test_un_error_real_en_una_ruta_de_la_api_si_escribe_traza_en_el_log(): void
    {
        $log = Log::spy();

        Route::get('/api/_prueba-error-real', function () {
            throw new RuntimeException('falla real');
        });

        $this->getJson('/api/_prueba-error-real')->assertStatus(500);

        $log->shouldHaveReceived('error')->once();
    }

    public function test_el_nombre_de_una_persona_en_el_mensaje_no_llega_al_log(): void
    {
        $log = Log::spy();

        $this->app->make(ExceptionHandler::class)
            ->report(new DuplicateAccountException('Lucía Terrazas Paz'));

        $log->shouldNotHaveReceived('error');
    }

    /** @return array<int, class-string> */
    private function dontReport(): array
    {
        $property = (new ReflectionClass(Handler::class))->getProperty('dontReport');
        $property->setAccessible(true);

        return $property->getValue($this->app->make(ExceptionHandler::class));
    }

    /** @return array<int, class-string> */
    private function exceptionClasses(): array
    {
        $classes = [];

        foreach (glob(app_path('Exceptions/*/*Exception.php')) as $file) {
            $classes[] = 'App\\Exceptions\\' . basename(dirname($file)) . '\\' . basename($file, '.php');
        }

        sort($classes);

        $this->assertGreaterThanOrEqual(15, count($classes), 'No se encontraron las excepciones de app/Exceptions.');

        return $classes;
    }

    /** Crea la excepción con los argumentos que pida su constructor. */
    private function makeException(string $class): \Throwable
    {
        $constructor = (new ReflectionClass($class))->getConstructor();

        // Sin constructor propio se usa el de Exception: basta el mensaje.
        if ($constructor === null || $constructor->getDeclaringClass()->getName() !== $class) {
            return new $class('Texto de prueba');
        }

        $arguments = [];

        foreach ($constructor->getParameters() as $parameter) {
            $type = (string) $parameter->getType();
            $arguments[] = $type === 'int' ? 422 : ($type === 'array' ? [] : 'Texto de prueba');
        }

        return new $class(...$arguments);
    }
}
