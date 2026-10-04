<?php

namespace Tests\Feature\Seeders;

use Database\Seeders\Support\LocalDatabaseGuard;
use Database\Seeders\TestData\AccountTestDataSeeder;
use Database\Seeders\TestData\CatalogTestDataSeeder;
use Database\Seeders\TestData\ExamTestDataSeeder;
use Database\Seeders\TestData\GroupTestDataSeeder;
use Database\Seeders\TestData\TestDataSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * Los seeders con ids fijos se niegan a escribir si el host de la base no es local. El host se simula
 * con la configuración: la conexión ya abierta a sciem_test no se vuelve a abrir, así que nunca se
 * conecta a ningún lado.
 */
class LocalDatabaseGuardTest extends TestCase
{
    use DatabaseTransactions;

    private const REMOTE_HOST = 'aws-0-us-west-2.pooler.supabase.com';

    private function connectTo(?string $host): void
    {
        config()->set('database.connections.' . config('database.default') . '.host', $host);
    }

    /** @return array<string, array{0: string}> */
    public function localHosts(): array
    {
        return [
            'servicio de Docker' => ['postgres'],
            'loopback' => ['127.0.0.1'],
            'localhost' => ['localhost'],
            'mayúsculas' => ['LOCALHOST'],
            'con espacios' => [' postgres '],
        ];
    }

    /** @dataProvider localHosts */
    public function test_acepta_los_hosts_locales(string $host): void
    {
        $this->connectTo($host);

        LocalDatabaseGuard::assertLocal('Seeder');

        $this->addToAssertionCount(1);
    }

    /** @return array<string, array{0: string|null}> */
    public function remoteHosts(): array
    {
        return [
            'Supabase' => [self::REMOTE_HOST],
            'otro servicio de Docker' => ['db'],
            'IP de la red' => ['10.0.0.5'],
            'subdominio de un nombre local' => ['localhost.example.com'],
            'prefijo de un nombre local' => ['postgres.example.com'],
            'vacío' => [''],
            'sin host' => [null],
        ];
    }

    /** @dataProvider remoteHosts */
    public function test_rechaza_los_hosts_no_locales(?string $host): void
    {
        $this->connectTo($host);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No se escribió nada');

        LocalDatabaseGuard::assertLocal('Seeder');
    }

    public function test_el_mensaje_nombra_al_seeder_y_el_host_sin_credenciales(): void
    {
        $this->connectTo(self::REMOTE_HOST);
        config()->set('database.connections.pgsql.password', 'secreto-que-no-debe-aparecer');

        try {
            LocalDatabaseGuard::assertLocal('MiSeeder');
            $this->fail('La guarda debía rechazar el host.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('MiSeeder', $exception->getMessage());
            $this->assertStringContainsString(self::REMOTE_HOST, $exception->getMessage());
            $this->assertStringNotContainsString('secreto-que-no-debe-aparecer', $exception->getMessage());
        }
    }

    /** @return array<string, array{0: class-string, 1: string}> seeder y la tabla que escribiría primero */
    public function fixedIdSeeders(): array
    {
        return [
            'TestDataSeeder' => [TestDataSeeder::class, 'facultad'],
            'CatalogTestDataSeeder' => [CatalogTestDataSeeder::class, 'facultad'],
            'AccountTestDataSeeder' => [AccountTestDataSeeder::class, 'usuario'],
            'GroupTestDataSeeder' => [GroupTestDataSeeder::class, 'estudiante'],
            'ExamTestDataSeeder' => [ExamTestDataSeeder::class, 'examen'],
        ];
    }

    /** @dataProvider fixedIdSeeders */
    public function test_los_seeders_con_ids_fijos_se_niegan_a_escribir_en_un_host_remoto(string $seeder, string $table): void
    {
        $this->connectTo(self::REMOTE_HOST);
        $before = DB::table($table)->count();

        try {
            app($seeder)->run();
            $this->fail("{$seeder} debía negarse a escribir.");
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString($seeder, $exception->getMessage());
            $this->assertStringContainsString('No se escribió nada', $exception->getMessage());
        }

        $this->assertSame($before, DB::table($table)->count());
    }
}
