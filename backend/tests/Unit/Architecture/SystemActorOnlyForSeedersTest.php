<?php

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Guarda la frontera entre las dos identidades de docs/architecture/usuario-actual.md:
 * la API actúa como la cuenta autenticada (CurrentUser) y los seeders como la cuenta de
 * sistema (SystemActor). Si SystemActor se colara en un Controller, un Request o un Service,
 * una operación de la API podría quedar firmada por una cuenta que nadie autenticó.
 */
class SystemActorOnlyForSeedersTest extends TestCase
{
    public function test_el_codigo_de_la_api_no_usa_la_cuenta_de_sistema(): void
    {
        $violations = [];

        foreach ($this->phpFiles('app') as $file) {
            if (basename($file) === 'SystemActor.php') {
                continue;
            }

            $code = $this->codeWithoutComments($file);

            foreach (['SystemActor', 'sciem.usuario_prueba'] as $needle) {
                if (strpos($code, $needle) !== false) {
                    $violations[] = $this->relative($file) . " usa {$needle}";
                }
            }
        }

        $this->assertSame(
            [],
            $violations,
            "Solo los seeders pueden actuar como la cuenta de sistema:\n" . implode("\n", $violations)
        );
    }

    public function test_los_seeders_no_resuelven_la_sesion(): void
    {
        $violations = [];

        foreach ($this->phpFiles('database/seeders') as $file) {
            $code = $this->codeWithoutComments($file);

            foreach (['CurrentUser', 'auth(', 'Auth::'] as $needle) {
                if (strpos($code, $needle) !== false) {
                    $violations[] = $this->relative($file) . " usa {$needle}";
                }
            }
        }

        $this->assertSame(
            [],
            $violations,
            "Los seeders no tienen sesión: usan SystemActor.\n" . implode("\n", $violations)
        );
    }

    /** @return array<int, string> */
    private function phpFiles(string $directory): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(dirname(__DIR__, 3) . '/' . $directory)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    /** El código sin comentarios: un docblock puede nombrar la clase sin usarla. */
    private function codeWithoutComments(string $file): string
    {
        $code = '';

        foreach (token_get_all(file_get_contents($file)) as $token) {
            if (is_array($token)) {
                if (! in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                    $code .= $token[1];
                }

                continue;
            }

            $code .= $token;
        }

        return $code;
    }

    private function relative(string $file): string
    {
        return substr($file, strlen(dirname(__DIR__, 3)) + 1);
    }
}
