<?php

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Guarda que un registro rectificado (retirado de la Central de Riesgo, HU-062) no vuelva a contar como
 * antecedente en ninguna lectura nueva.
 *
 * Toda lectura Eloquent pasa por el scope global de RiskCenter. Esta prueba vigila lo que el scope no cubre:
 * quien nombre la tabla central_riesgo en SQL (DB::table, joins, selectRaw) debe estar en la lista y filtrar
 * `rectificado_en`, y solo el módulo de rectificación y el aviso de HU-045 pueden pedir los rectificados.
 */
class CentralRiesgoReadersIgnoreRectifiedTest extends TestCase
{
    /**
     * Archivos de app/ que nombran la tabla en SQL y deben filtrar `rectificado_en` en cada lectura.
     *
     * @var array<int, string>
     */
    private const SQL_READERS = [
        'app/Services/Exams/ExamHistoryService.php',
        'app/Services/Reports/Academic/AlienAttemptsReportService.php',
        'app/Services/RiskCenter/Academic/RiskCenterQueryService.php',
        'app/Models/StudentReport.php',
    ];

    /**
     * Archivos que nombran la tabla sin leerla: el modelo que declara `$table`, el scope que lo filtra y la
     * bitácora de la rectificación, que solo usa el nombre como `tabla_afectada`.
     *
     * @var array<int, string>
     */
    private const NAME_ONLY = [
        'app/Models/RiskCenter.php',
        'app/Models/Scopes/ActiveRiskRecordScope.php',
        'app/Services/RiskCenter/Academic/RiskCenterRectificationService.php',
    ];

    /**
     * Archivos autorizados a ver los registros rectificados (withVoided o quitar el scope).
     *
     * @var array<int, string>
     */
    private const MAY_SEE_RECTIFIED = [
        'app/Models/RiskCenter.php',
        'app/Models/StudentReport.php',
        'app/Models/Scopes/ActiveRiskRecordScope.php',
        'app/Services/RiskCenter/Academic/RiskCenterRectificationQueryService.php',
        'app/Services/RiskCenter/Academic/RiskCenterRectificationService.php',
    ];

    public function test_quien_nombra_la_tabla_en_sql_esta_en_la_lista_y_filtra_los_rectificados(): void
    {
        $allowed = array_merge(self::SQL_READERS, self::NAME_ONLY);
        $unlisted = [];
        $unfiltered = [];

        foreach ($this->phpFiles('app') as $file) {
            $code = $this->codeWithoutComments($file);
            $relative = $this->relative($file);

            if (preg_match('/(?<![A-Za-z_])central_riesgo(?![A-Za-z_])/', $code) !== 1) {
                continue;
            }

            if (! in_array($relative, $allowed, true)) {
                $unlisted[] = $relative;
            }

            if (in_array($relative, self::SQL_READERS, true) && strpos($code, 'rectificado_en') === false) {
                $unfiltered[] = $relative;
            }
        }

        $this->assertSame(
            [],
            $unlisted,
            "Estos archivos leen central_riesgo y no están en la lista. Filtre `rectificado_en IS NULL` y agréguelos "
            . "a SQL_READERS:\n" . implode("\n", $unlisted)
        );
        $this->assertSame(
            [],
            $unfiltered,
            "Estos lectores de central_riesgo no filtran los registros rectificados:\n" . implode("\n", $unfiltered)
        );
    }

    public function test_solo_el_modulo_de_rectificacion_puede_ver_los_registros_rectificados(): void
    {
        $violations = [];

        foreach ($this->phpFiles('app') as $file) {
            $code = $this->codeWithoutComments($file);
            $relative = $this->relative($file);

            foreach (['withVoided(', 'ActiveRiskRecordScope'] as $needle) {
                if (strpos($code, $needle) !== false && ! in_array($relative, self::MAY_SEE_RECTIFIED, true)) {
                    $violations[] = "{$relative} usa {$needle}";
                }
            }
        }

        $this->assertSame(
            [],
            $violations,
            "Un registro rectificado ya no es antecedente; solo estos archivos pueden verlo:\n"
            . implode("\n", $violations)
        );
    }

    public function test_las_listas_apuntan_a_archivos_que_existen(): void
    {
        foreach (array_merge(self::SQL_READERS, self::NAME_ONLY, self::MAY_SEE_RECTIFIED) as $relative) {
            $this->assertFileExists(dirname(__DIR__, 3) . '/' . $relative);
        }
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

    /** El código sin comentarios: un docblock puede nombrar la tabla sin leerla. */
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
        return str_replace('\\', '/', substr($file, strlen(dirname(__DIR__, 3)) + 1));
    }
}
