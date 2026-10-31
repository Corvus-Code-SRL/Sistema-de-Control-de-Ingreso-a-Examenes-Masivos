<?php

namespace Tests\Feature\Academic;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsRosterFiles;
use Tests\Concerns\SeedsAssistantManagement;
use Tests\TestCase;

/**
 * HU-21 (CA 15): límites de la carga. 10 MB de archivo, 2000 filas (configurable) y mensajes
 * en castellano cuando PHP o el servidor descartan el archivo antes de que lo vea la aplicación.
 */
class StudentRosterLimitsTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsAssistantManagement;
    use BuildsRosterFiles;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAcademicCatalog();
    }

    /* ------------------------------ tope de filas ------------------------------ */

    public function test_el_tope_por_defecto_es_de_2000_filas(): void
    {
        $this->assertSame(2000, config('sciem.nomina_max_filas'));
    }

    public function test_un_csv_con_exactamente_2000_filas_se_previsualiza(): void
    {
        $this->previewRoster($this->csvFile($this->rosterRows(2000)), $this->grupoPropioId)
            ->assertOk()
            ->assertJsonPath('data.total_filas', 2000);
    }

    public function test_un_csv_con_mas_de_2000_filas_responde_422_diciendo_el_limite(): void
    {
        $before = DB::table('estudiante')->count();

        $this->previewRoster($this->csvFile($this->rosterRows(2001)), $this->grupoPropioId)
            ->assertStatus(422)
            ->assertJsonPath('message', 'La nómina supera el máximo de 2000 filas por archivo.');

        $this->assertSame($before, DB::table('estudiante')->count());
    }

    public function test_un_xlsx_con_mas_de_2000_filas_responde_422_diciendo_el_limite(): void
    {
        $this->previewRoster($this->xlsxFile($this->rosterRows(2001)), $this->grupoPropioId)
            ->assertStatus(422)
            ->assertJsonPath('message', 'La nómina supera el máximo de 2000 filas por archivo.');
    }

    public function test_un_xlsx_con_exactamente_2000_filas_se_previsualiza(): void
    {
        $this->previewRoster($this->xlsxFile($this->rosterRows(2000)), $this->grupoPropioId)
            ->assertOk()
            ->assertJsonPath('data.total_filas', 2000);
    }

    public function test_el_tope_sale_de_la_configuracion(): void
    {
        config()->set('sciem.nomina_max_filas', 3);

        $this->previewRoster($this->csvFile($this->rosterRows(3)), $this->grupoPropioId)->assertOk();
        $this->previewRoster($this->csvFile($this->rosterRows(4)), $this->grupoPropioId)
            ->assertStatus(422)
            ->assertJsonPath('message', 'La nómina supera el máximo de 3 filas por archivo.');
        $this->previewRoster($this->xlsxFile($this->rosterRows(4)), $this->grupoPropioId)
            ->assertStatus(422)
            ->assertJsonPath('message', 'La nómina supera el máximo de 3 filas por archivo.');
    }

    /* ----------------------------- errores de subida ----------------------------- */

    public function test_un_archivo_que_pasa_el_limite_de_php_responde_422_en_castellano(): void
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('sciem_roster_', true) . '.csv';
        file_put_contents($path, '');
        $this->rosterTemporaryFiles[] = $path;
        $file = new UploadedFile($path, 'nomina.csv', null, UPLOAD_ERR_INI_SIZE, true);

        $response = $this->previewRoster($file, $this->grupoPropioId);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('archivo');
        $this->assertSame(
            'No se pudo recibir la nómina. Verifique que no supere los 10 MB y vuelva a intentarlo.',
            $response->json('errors.archivo.0')
        );
        $this->assertStringNotContainsString('failed to upload', (string) $response->getContent());
    }

    public function test_un_cuerpo_mas_grande_que_post_max_size_responde_413_en_castellano_en_la_nomina(): void
    {
        $request = Request::create('/api/grupos/1/nomina/preview', 'POST');
        $request->headers->set('Accept', 'application/json');

        $response = $this->app->make(ExceptionHandler::class)->render($request, new PostTooLargeException());

        $this->assertSame(413, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertSame('La nómina no puede superar los 10 MB.', $body['message']);
    }

    public function test_el_mensaje_de_la_nomina_no_se_aplica_a_otras_rutas(): void
    {
        $request = Request::create('/api/grupos', 'POST');
        $request->headers->set('Accept', 'application/json');

        $response = $this->app->make(ExceptionHandler::class)->render($request, new PostTooLargeException());

        $this->assertSame(413, $response->getStatusCode());
        $this->assertStringNotContainsString('nómina', (string) $response->getContent());
    }
}
