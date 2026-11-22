<?php

namespace Tests\Feature\Maintenance;

use App\Services\Maintenance\DatabaseHealthService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** RNF-14: php artisan sciem:salud-bd muestra el estado de la base sin cambiar nada. */
class DatabaseHealthCommandTest extends TestCase
{
    private const OTHER = 'pgsql_salud';

    protected function tearDown(): void
    {
        if (array_key_exists(self::OTHER, DB::getConnections())) {
            DB::connection(self::OTHER)->rollBack(0);
        }
        DB::purge(self::OTHER);

        parent::tearDown();
    }

    public function test_muestra_todas_las_secciones(): void
    {
        $exitCode = Artisan::call('sciem:salud-bd');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $sections = [
            'Conexiones:',
            'Sesiones de esta base por aplicación y estado:',
            'Consultas o transacciones abiertas hace 30 s o más:',
            'Sesiones esperando un lock:',
            'Tablas con más filas muertas',
        ];

        foreach ($sections as $section) {
            $this->assertStringContainsString($section, $output);
        }
    }

    public function test_detecta_otra_sesion_con_una_transaccion_abierta(): void
    {
        config(['database.connections.' . self::OTHER => array_merge(
            config('database.connections.pgsql'),
            ['application_name' => 'otraapp']
        )]);
        DB::connection(self::OTHER)->beginTransaction();
        DB::connection(self::OTHER)->select('select 1');

        Artisan::call('sciem:salud-bd', ['--umbral' => 0]);
        $output = Artisan::output();

        $this->assertStringContainsString('otraapp-consola', $output);
        $this->assertStringContainsString('idle in transaction', $output);
        $this->assertStringContainsString('Una transacción abierta y quieta retiene locks', $output);
    }

    public function test_el_servicio_no_cuenta_su_propia_sesion(): void
    {
        $pid = (int) DB::selectOne('select pg_backend_pid() as pid')->pid;
        $health = app(DatabaseHealthService::class);

        $this->assertNotContains($pid, array_column($health->longSessions(0), 'pid'));
        $this->assertNotContains($pid, array_column($health->blockedSessions(), 'pid'));
    }

    public function test_el_servicio_lista_filas_vivas_y_muertas_por_tabla(): void
    {
        $tables = array_column(app(DatabaseHealthService::class)->deadRows(500), 'tabla');

        foreach (['examen_estudiante', 'intento_ingreso', 'log'] as $table) {
            $this->assertContains($table, $tables);
        }

        $connections = app(DatabaseHealthService::class)->connections();
        $this->assertGreaterThan(0, $connections['usadas']);
        $this->assertGreaterThanOrEqual($connections['usadas'], $connections['maximo']);
    }
}
