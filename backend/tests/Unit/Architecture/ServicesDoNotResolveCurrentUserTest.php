<?php

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Guarda la regla de docs/architecture/usuario-actual.md: los Services reciben al usuario
 * como argumento y nunca lo resuelven ellos mismos, para que las pruebas puedan decidir
 * quién actúa.
 */
class ServicesDoNotResolveCurrentUserTest extends TestCase
{
    private const FORBIDDEN = [
        'auth(' => 'auth()',
        'Auth::' => 'la fachada Auth',
        'CurrentUser' => 'el resolver CurrentUser',
        'SystemActor' => 'la cuenta de sistema SystemActor',
        'sciem.usuario_prueba' => 'config(sciem.usuario_prueba)',
        '->user()' => 'el usuario de la petición',
    ];

    public function test_ningun_service_resuelve_el_usuario_actual(): void
    {
        $violations = [];

        foreach ($this->serviceFiles() as $file) {
            $code = file_get_contents($file);

            foreach (self::FORBIDDEN as $needle => $label) {
                if (strpos($code, $needle) !== false) {
                    $violations[] = substr($file, strlen(dirname(__DIR__, 3)) + 1) . " usa {$label}";
                }
            }
        }

        $this->assertSame(
            [],
            $violations,
            "Los Services reciben el usuario por argumento desde el Controller:\n" . implode("\n", $violations)
        );
    }

    /** @return array<int, string> */
    private function serviceFiles(): array
    {
        $root = dirname(__DIR__, 3) . '/app/Services';
        $files = [];

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
