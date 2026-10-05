<?php

namespace Tests\Feature\Academic;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsRosterFiles;
use Tests\Concerns\SeedsAssistantManagement;
use Tests\TestCase;

/**
 * HU-21 (CA 2 y 15): un CSV exportado desde Excel o WebSIS suele venir en Windows-1252.
 * Se lee como tal; un archivo que no es texto se rechaza con un 422 claro.
 */
class StudentRosterEncodingTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsAssistantManagement;
    use BuildsRosterFiles;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAcademicCatalog();
    }

    private function windows1252Csv(): string
    {
        $utf8 = $this->csvContent([
            ['300000001', 'MUÑOZ SOLIZ', 'JOSÉ'],
            ['300000002', 'PEÑA ROJAS', 'ANA'],
        ]);

        return mb_convert_encoding($utf8, 'Windows-1252', 'UTF-8');
    }

    public function test_un_csv_en_windows_1252_se_previsualiza_con_200(): void
    {
        $content = $this->windows1252Csv();
        $this->assertFalse(mb_check_encoding($content, 'UTF-8'), 'el fixture debe ser realmente Windows-1252');

        $response = $this->previewRoster($this->rosterFile('nomina.csv', $content), $this->grupoPropioId);

        $response->assertOk();
        $response->assertJsonPath('data.total_filas', 2);
        $response->assertJsonPath('data.filas_inconsistentes', 0);
        $response->assertJsonPath('data.filas.0.apellidos', 'MUÑOZ SOLIZ');
        $response->assertJsonPath('data.filas.0.nombres', 'JOSÉ');
        $response->assertJsonPath('data.filas.1.apellidos', 'PEÑA ROJAS');
    }

    public function test_los_nombres_de_un_csv_en_windows_1252_se_guardan_bien_al_confirmar(): void
    {
        $preview = $this->previewRoster(
            $this->rosterFile('nomina.csv', $this->windows1252Csv()),
            $this->grupoPropioId
        );

        $this->confirmRoster($preview->json('data.token'), $this->grupoPropioId)
            ->assertOk()
            ->assertJsonPath('data.estudiantes_creados', 2);

        $this->assertDatabaseHas('estudiante', [
            'cod_sis' => '300000001',
            'apellido_paterno' => 'MUÑOZ SOLIZ',
            'nombre' => 'JOSÉ',
        ]);
        $this->assertDatabaseHas('estudiante', [
            'cod_sis' => '300000002',
            'apellido_paterno' => 'PEÑA ROJAS',
        ]);
        $this->assertTrue(
            mb_check_encoding((string) DB::table('estudiante')->where('cod_sis', '300000001')->value('nombre'), 'UTF-8')
        );
    }

    public function test_un_csv_en_utf_8_con_bom_sigue_funcionando(): void
    {
        $content = "\xEF\xBB\xBF" . $this->csvContent([['300000001', 'MUÑOZ', 'JOSÉ']]);

        $this->previewRoster($this->rosterFile('nomina.csv', $content), $this->grupoPropioId)
            ->assertOk()
            ->assertJsonPath('data.filas.0.apellidos', 'MUÑOZ');
    }

    /**
     * @dataProvider notTextFiles
     */
    public function test_un_archivo_que_no_es_texto_responde_422_en_castellano(string $content): void
    {
        $before = DB::table('estudiante')->count();

        $response = $this->previewRoster($this->rosterFile('nomina.csv', $content), $this->grupoPropioId);

        $response->assertStatus(422);
        $response->assertJsonPath(
            'message',
            'El archivo CSV no es un archivo de texto. Guárdelo como CSV con codificación UTF-8 y vuelva a cargarlo.'
        );
        $this->assertSame($before, DB::table('estudiante')->count());
    }

    /** @return array<string, array{0: string}> */
    public function notTextFiles(): array
    {
        return [
            'binario con bytes nulos' => ["\x00\x01\x02\x03\xFF\xFE\x00\x10" . str_repeat("\x00\x80", 40)],
            'cabecera de imagen PNG' => ["\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01"],
            'texto en UTF-16' => [
                mb_convert_encoding($this->csvContent([['300000001', 'PEREZ', 'ANA']]), 'UTF-16LE', 'UTF-8'),
            ],
        ];
    }
}
