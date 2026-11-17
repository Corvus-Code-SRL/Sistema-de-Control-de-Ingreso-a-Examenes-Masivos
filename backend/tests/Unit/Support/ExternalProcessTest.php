<?php

namespace Tests\Unit\Support;

use App\Support\Process\ExternalProcess;
use PHPUnit\Framework\TestCase;

class ExternalProcessTest extends TestCase
{
    public function test_devuelve_el_codigo_de_salida_la_salida_y_los_errores(): void
    {
        $result = (new ExternalProcess())->run(
            [PHP_BINARY, '-r', 'echo getenv("SCIEM_PRUEBA"); fwrite(STDERR, " falla "); exit(3);'],
            ['SCIEM_PRUEBA' => 'valor con espacios'],
            60
        );

        $this->assertFalse($result->successful());
        $this->assertSame(3, $result->exitCode);
        $this->assertSame('falla', $result->errorOutput);
        $this->assertSame('valor con espacios', $result->output);
    }

    public function test_los_argumentos_no_pasan_por_una_shell(): void
    {
        $result = (new ExternalProcess())->run([PHP_BINARY, '-r', 'echo $argv[1];', '$(whoami); echo x'], [], 60);

        $this->assertTrue($result->successful());
        $this->assertSame('$(whoami); echo x', $result->output);
    }

    public function test_una_ruta_con_espacios_se_pasa_tal_cual(): void
    {
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sciem prueba ' . bin2hex(random_bytes(4));
        mkdir($directory);
        $script = $directory . DIRECTORY_SEPARATOR . 'programa con espacios.php';
        file_put_contents($script, '<?php echo $argv[1];');

        try {
            $result = (new ExternalProcess())->run([PHP_BINARY, $script, 'argumento con espacios'], [], 60);
        } finally {
            unlink($script);
            rmdir($directory);
        }

        $this->assertTrue($result->successful(), $result->errorOutput);
        $this->assertSame('argumento con espacios', $result->output);
    }

    public function test_un_programa_inexistente_devuelve_127_con_un_mensaje_claro(): void
    {
        $result = (new ExternalProcess())->run(['/no existe/pg_dump_inexistente'], [], 60);

        $this->assertFalse($result->successful());
        $this->assertSame(127, $result->exitCode);
        $this->assertStringContainsString('/no existe/pg_dump_inexistente', $result->errorOutput);
    }

    public function test_en_windows_una_ruta_sin_extension_encuentra_el_ejecutable(): void
    {
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sciem_programa_' . bin2hex(random_bytes(4));
        mkdir($directory);
        $program = $directory . DIRECTORY_SEPARATOR . 'pg_dump';
        touch($program . '.exe');

        try {
            // Como cmd.exe: «...\pg_dump» vale si existe «...\pg_dump.exe».
            $this->assertTrue(ExternalProcess::programExists($program, true, '.COM;.EXE;.BAT;.CMD'));
            $this->assertTrue(ExternalProcess::programExists($program, true));
            $this->assertTrue(ExternalProcess::programExists($program . '.exe', true));
            // Fuera de Windows la ruta debe ser exacta.
            $this->assertFalse(ExternalProcess::programExists($program, false));
            // Una ruta con otra extensión no se completa.
            $this->assertFalse(ExternalProcess::programExists($program . '.bin', true));
            $this->assertFalse(ExternalProcess::programExists($directory . DIRECTORY_SEPARATOR . 'otro', true));
        } finally {
            unlink($program . '.exe');
            rmdir($directory);
        }
    }

    public function test_un_nombre_suelto_se_busca_en_el_path(): void
    {
        $this->assertTrue(ExternalProcess::programExists(basename(PHP_BINARY), DIRECTORY_SEPARATOR === '\\')
            || ExternalProcess::programExists(PHP_BINARY, DIRECTORY_SEPARATOR === '\\'));
        $this->assertFalse(ExternalProcess::programExists('pg_dump_inexistente_sciem', false));
        $this->assertFalse(ExternalProcess::programExists('', false));
    }

    public function test_supera_el_tiempo_maximo_y_devuelve_124(): void
    {
        $result = (new ExternalProcess())->run([PHP_BINARY, '-r', 'sleep(5);'], [], 1);

        $this->assertSame(124, $result->exitCode);
    }
}
