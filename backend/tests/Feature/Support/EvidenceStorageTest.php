<?php

namespace Tests\Feature\Support;

use App\Support\Storage\EvidenceStorage;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Resolución de evidencias en los discos configurados, sin storage:link ni APP_URL. */
class EvidenceStorageTest extends TestCase
{
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    private EvidenceStorage $storage;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
        config(['sciem.evidencia_discos' => ['local', 'public']]);

        $this->storage = new EvidenceStorage();
    }

    public function test_encuentra_el_archivo_en_el_disco_publico(): void
    {
        Storage::disk('public')->put('evidencias/reportes/1.png', base64_decode(self::PNG));

        $this->assertSame(
            ['disk' => 'public', 'path' => 'evidencias/reportes/1.png'],
            $this->storage->locate('evidencias/reportes/1.png')
        );
    }

    public function test_encuentra_el_archivo_en_el_disco_privado_y_lo_prefiere(): void
    {
        Storage::disk('local')->put('evidencias/reportes/2.png', base64_decode(self::PNG));
        Storage::disk('public')->put('evidencias/reportes/2.png', base64_decode(self::PNG));

        $this->assertSame('local', $this->storage->locate('evidencias/reportes/2.png')['disk']);
    }

    public function test_describe_da_nombre_y_tamano_y_marca_si_el_archivo_falta(): void
    {
        Storage::disk('local')->put('evidencias/reportes/3.png', 'abcd');

        $this->assertSame(
            ['nombre_archivo' => '3.png', 'tamano_bytes' => 4, 'disponible' => true],
            $this->storage->describe('evidencias/reportes/3.png')
        );
        $this->assertSame(
            ['nombre_archivo' => 'falta.png', 'tamano_bytes' => null, 'disponible' => false],
            $this->storage->describe('evidencias/reportes/falta.png')
        );
        $this->assertNull($this->storage->describe(null));
    }

    /**
     * @dataProvider unsafePaths
     */
    public function test_rechaza_rutas_que_salen_de_evidencias(?string $path): void
    {
        Storage::disk('local')->put('secreto.txt', 'no debe salir');
        Storage::disk('public')->put('secreto.txt', 'no debe salir');

        $this->assertNull($this->storage->safePath($path));
        $this->assertNull($this->storage->locate($path));
    }

    public function unsafePaths(): array
    {
        return [
            'nula' => [null],
            'vacia' => [''],
            'fuera de evidencias' => ['secreto.txt'],
            'otra carpeta' => ['otra/evidencias/1.png'],
            'prefijo parecido' => ['evidencias-otras/1.png'],
            'ruta absoluta' => ['/evidencias/1.png'],
            'sube con puntos' => ['evidencias/../secreto.txt'],
            'sube al final' => ['evidencias/reportes/..'],
            'segmento punto' => ['evidencias/' . '.' . '/1.png'],
            'doble barra' => ['evidencias//1.png'],
            'barra invertida' => ['evidencias\\..\\secreto.txt'],
            'byte nulo' => ["evidencias/1.png\0.txt"],
            'salto de linea' => ["evidencias/1.png\n"],
        ];
    }

    public function test_el_svg_se_descarga_y_la_imagen_se_muestra(): void
    {
        Storage::disk('local')->put('evidencias/reportes/a.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>');
        Storage::disk('local')->put('evidencias/reportes/b.png', base64_decode(self::PNG));

        $svg = $this->storage->response(['disk' => 'local', 'path' => 'evidencias/reportes/a.svg']);
        $png = $this->storage->response(['disk' => 'local', 'path' => 'evidencias/reportes/b.png']);

        $this->assertStringStartsWith('attachment', $svg->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('inline', $png->headers->get('Content-Disposition'));
        $this->assertSame('image/png', $png->headers->get('Content-Type'));
        $this->assertSame('nosniff', $png->headers->get('X-Content-Type-Options'));
    }
}
