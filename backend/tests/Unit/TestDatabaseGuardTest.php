<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\TestCase as SuiteTestCase;

/**
 * La guarda que protege la base antes de que la suite borre el esquema. Se prueba la regla pura:
 * no abre ninguna conexión.
 */
class TestDatabaseGuardTest extends TestCase
{
    private const REMOTE_HOST = 'aws-0-us-west-2.pooler.supabase.com';

    /** @return array<string, array{0: string}> */
    public function validHosts(): array
    {
        return [
            'loopback' => ['127.0.0.1'],
            'localhost' => ['localhost'],
            'loopback IPv6' => ['::1'],
            'servicio de Docker' => ['postgres'],
            'mayúsculas' => ['LOCALHOST'],
            'con espacios' => [' postgres '],
        ];
    }

    /** @dataProvider validHosts */
    public function test_acepta_sciem_test_en_un_host_local(string $host): void
    {
        SuiteTestCase::assertTestDatabase('sciem_test', $host);

        $this->addToAssertionCount(1);
    }

    /** @return array<string, array{0: string|null}> */
    public function invalidDatabases(): array
    {
        return [
            'base de desarrollo' => ['sciem_db'],
            'base por defecto' => ['postgres'],
            'otra capitalización' => ['Sciem_Test'],
            'con sufijo' => ['sciem_test_2'],
            'vacío' => [''],
            'sin nombre' => [null],
        ];
    }

    /** @dataProvider invalidDatabases */
    public function test_rechaza_una_base_que_no_es_sciem_test(?string $database): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No se modificó nada');

        SuiteTestCase::assertTestDatabase($database, 'postgres');
    }

    /** @return array<string, array{0: mixed}> */
    public function invalidHosts(): array
    {
        return [
            'Supabase' => [self::REMOTE_HOST],
            'otro servicio de Docker' => ['db'],
            'IP de la red' => ['10.0.0.5'],
            'subdominio de un nombre local' => ['localhost.example.com'],
            'prefijo de un nombre local' => ['postgres.example.com'],
            'vacío' => [''],
            'sin host' => [null],
            'varios hosts' => [['127.0.0.1', self::REMOTE_HOST]],
        ];
    }

    /**
     * @dataProvider invalidHosts
     *
     * @param  mixed  $host
     */
    public function test_rechaza_un_host_que_no_es_local($host): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No se modificó nada');

        SuiteTestCase::assertTestDatabase('sciem_test', $host);
    }

    public function test_el_mensaje_nombra_la_base_y_el_host_y_no_trae_credenciales(): void
    {
        try {
            SuiteTestCase::assertTestDatabase('sciem_db', self::REMOTE_HOST);
            $this->fail('La guarda debía abortar.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('sciem_db', $exception->getMessage());
            $this->assertStringContainsString(self::REMOTE_HOST, $exception->getMessage());
            $this->assertStringContainsString('DB_DATABASE=sciem_test', $exception->getMessage());
            $this->assertStringContainsString('127.0.0.1, localhost, ::1, postgres', $exception->getMessage());
            $this->assertStringNotContainsStringIgnoringCase('password', $exception->getMessage());
        }
    }
}
