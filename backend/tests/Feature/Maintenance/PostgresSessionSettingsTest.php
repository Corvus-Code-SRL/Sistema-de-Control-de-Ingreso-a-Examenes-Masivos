<?php

namespace Tests\Feature\Maintenance;

use App\Exceptions\Handler;
use App\Support\Database\SciemPostgresConnector;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PDO;
use PDOException;
use Tests\TestCase;

/** RNF-14: nombre de aplicación y límites de sesión que pone el conector de PostgreSQL. */
class PostgresSessionSettingsTest extends TestCase
{
    private const EXTRA = 'pgsql_rnf14';

    protected function tearDown(): void
    {
        DB::purge(self::EXTRA);

        parent::tearDown();
    }

    /** @param  array<string, mixed>  $overrides */
    private function config(array $overrides = []): array
    {
        return array_merge(config('database.connections.pgsql'), $overrides);
    }

    private function setting(PDO $pdo, string $name): string
    {
        $statement = $pdo->prepare('select setting from pg_settings where name = ?');
        $statement->execute([$name]);

        return (string) $statement->fetchColumn();
    }

    public function test_la_conexion_usa_el_conector_de_sciem_y_se_identifica_como_consola(): void
    {
        $this->assertInstanceOf(SciemPostgresConnector::class, app('db.connector.pgsql'));
        $this->assertSame('sciem-consola', DB::selectOne("select current_setting('application_name') as v")->v);
    }

    public function test_en_una_peticion_web_aplica_los_limites_de_sesion(): void
    {
        $pdo = (new SciemPostgresConnector(false))->connect($this->config([
            'statement_timeout' => '1500',
            'lock_timeout' => 800,
            'idle_in_transaction_session_timeout' => '20000',
        ]));

        $this->assertSame('1500', $this->setting($pdo, 'statement_timeout'));
        $this->assertSame('800', $this->setting($pdo, 'lock_timeout'));
        $this->assertSame('20000', $this->setting($pdo, 'idle_in_transaction_session_timeout'));
        $this->assertSame('sciem-web', $this->setting($pdo, 'application_name'));
    }

    public function test_vacio_o_cero_no_pone_limite(): void
    {
        $pdo = (new SciemPostgresConnector(false))->connect($this->config([
            'statement_timeout' => null,
            'lock_timeout' => '',
            'idle_in_transaction_session_timeout' => 0,
        ]));

        foreach (array_keys(SciemPostgresConnector::TIMEOUTS) as $name) {
            $this->assertSame('0', $this->setting($pdo, $name), $name);
        }
    }

    public function test_en_consola_no_aplica_limites_salvo_que_se_pida(): void
    {
        $limits = ['statement_timeout' => 1500, 'lock_timeout' => 800];

        $console = (new SciemPostgresConnector(true))->connect($this->config($limits));
        $this->assertSame('0', $this->setting($console, 'statement_timeout'));
        $this->assertSame('0', $this->setting($console, 'lock_timeout'));

        $forced = (new SciemPostgresConnector(true))->connect(
            $this->config($limits + ['session_timeouts_in_console' => true])
        );
        $this->assertSame('1500', $this->setting($forced, 'statement_timeout'));
        $this->assertSame('800', $this->setting($forced, 'lock_timeout'));
    }

    public function test_el_nombre_de_aplicacion_solo_admite_letras_numeros_guion_y_guion_bajo(): void
    {
        $pdo = (new SciemPostgresConnector(false))->connect($this->config([
            'application_name' => "sciem'; drop table log; --",
        ]));

        $this->assertSame('sciemdroptablelog---web', $this->setting($pdo, 'application_name'));

        $empty = (new SciemPostgresConnector(false))->connect($this->config(['application_name' => "'' "]));
        $this->assertSame('sciem-web', $this->setting($empty, 'application_name'));
    }

    public function test_una_consulta_que_supera_el_limite_se_corta_y_responde_503(): void
    {
        config(['database.connections.' . self::EXTRA => $this->config([
            'statement_timeout' => 100,
            'session_timeouts_in_console' => true,
        ])]);

        try {
            DB::connection(self::EXTRA)->select('select pg_sleep(2)');
            $this->fail('La consulta debía cortarse por statement_timeout.');
        } catch (QueryException $exception) {
            $this->assertSame('57014', (string) $exception->getCode());
        }

        $response = $this->render($exception);

        $this->assertSame(503, $response->getStatusCode());
        $this->assertSame(Handler::BUSY_MESSAGE, $response->getData(true)['message']);
    }

    public function test_un_lock_no_disponible_responde_503_y_otros_errores_de_base_no(): void
    {
        $this->assertSame(503, $this->render($this->queryException('55P03'))->getStatusCode());
        $this->assertSame(500, $this->render($this->queryException('23505'))->getStatusCode());
    }

    private function queryException(string $sqlstate): QueryException
    {
        $previous = new class ('error de prueba') extends PDOException {
            public function setSqlState(string $sqlstate): void
            {
                $this->code = $sqlstate;
            }
        };
        $previous->setSqlState($sqlstate);

        return new QueryException('select 1', [], $previous);
    }

    /** @return \Illuminate\Http\JsonResponse */
    private function render(QueryException $exception)
    {
        $request = Request::create('/api/examenes', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']);

        return app(ExceptionHandler::class)->render($request, $exception);
    }
}
