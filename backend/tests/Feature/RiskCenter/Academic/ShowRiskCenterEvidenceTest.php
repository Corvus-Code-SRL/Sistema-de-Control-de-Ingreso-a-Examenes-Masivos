<?php

namespace Tests\Feature\RiskCenter\Academic;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SeedsRiskCenterRecords;
use Tests\Concerns\SeedsSecurityAccounts;
use Tests\TestCase;

/**
 * HU-046: GET /api/central-riesgo/{id}/evidencia entrega el archivo autenticado, leyéndolo del disco
 * (privado o público) y sin depender de storage:link ni de APP_URL.
 */
class ShowRiskCenterEvidenceTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsRiskCenterRecords;
    use SeedsSecurityAccounts;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    private array $context;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
        config(['sciem.evidencia_discos' => ['local', 'public']]);

        $this->seedSecurityAccounts();
        $this->context = $this->createReportingContext();
    }

    public function test_sin_token_responde_401(): void
    {
        $this->actAsGuest();

        $this->getJson('/api/central-riesgo/1/evidencia')->assertUnauthorized();
    }

    public function test_un_auxiliar_recibe_403_aunque_la_evidencia_exista(): void
    {
        $id = $this->recordWithEvidence('evidencias/reportes/1.png', base64_decode(self::PNG), 'public');
        $this->actAs($this->createAccount(Role::AUXILIAR));

        $this->getJson("/api/central-riesgo/{$id}/evidencia")->assertForbidden();
    }

    public function test_una_cuenta_inactiva_recibe_403(): void
    {
        $id = $this->recordWithEvidence('evidencias/reportes/1.png', base64_decode(self::PNG), 'public');
        $this->actAs($this->createAccount(Role::DOCENTE, User::ESTADO_INACTIVO));

        $this->getJson("/api/central-riesgo/{$id}/evidencia")->assertForbidden();
    }

    /** @return array<string, array{0: string}> */
    public function allowedRoles(): array
    {
        return ['docente' => [Role::DOCENTE], 'administrador' => [Role::ADMINISTRADOR]];
    }

    /**
     * @dataProvider allowedRoles
     */
    public function test_docente_y_administrador_reciben_el_archivo_con_sus_cabeceras(string $role): void
    {
        $content = base64_decode(self::PNG);
        $id = $this->recordWithEvidence('evidencias/reportes/foto_carnet.png', $content, 'public');
        $this->actAs($this->createAccount($role));

        $response = $this->get("/api/central-riesgo/{$id}/evidencia")->assertOk();

        $this->assertSame($content, $response->streamedContent());
        $response->assertHeader('Content-Type', 'image/png');
        $response->assertHeader('Content-Length', (string) strlen($content));
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('inline', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('filename=foto_carnet.png', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_un_pdf_se_entrega_como_application_pdf(): void
    {
        $id = $this->recordWithEvidence('evidencias/reportes/acta.pdf', "%PDF-1.4\n%%EOF\n", 'public');
        $this->actAs($this->createAccount(Role::DOCENTE));

        $this->get("/api/central-riesgo/{$id}/evidencia")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_un_archivo_que_el_navegador_podria_ejecutar_se_descarga_en_lugar_de_mostrarse(): void
    {
        $id = $this->recordWithEvidence('evidencias/reportes/pagina.html', '<script>alert(1)</script>', 'public');
        $this->actAs($this->createAccount(Role::DOCENTE));

        $response = $this->get("/api/central-riesgo/{$id}/evidencia")->assertOk();

        $this->assertStringStartsWith('attachment', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('sandbox', $response->headers->get('Content-Security-Policy'));
    }

    public function test_lee_el_archivo_del_disco_privado(): void
    {
        $id = $this->recordWithEvidence('evidencias/reportes/privada.png', base64_decode(self::PNG), 'local');
        $this->actAs($this->createAccount(Role::ADMINISTRADOR));

        $this->assertFalse(Storage::disk('public')->exists('evidencias/reportes/privada.png'));
        $this->get("/api/central-riesgo/{$id}/evidencia")->assertOk()->assertHeader('Content-Type', 'image/png');
    }

    public function test_si_el_archivo_esta_en_ambos_discos_gana_el_privado(): void
    {
        $id = $this->recordWithEvidence('evidencias/reportes/doble.txt', 'privado', 'local');
        Storage::disk('public')->put('evidencias/reportes/doble.txt', 'publico');
        $this->actAs($this->createAccount(Role::DOCENTE));

        $this->assertSame('privado', $this->get("/api/central-riesgo/{$id}/evidencia")->streamedContent());
    }

    public function test_no_depende_de_storage_link_ni_de_app_url(): void
    {
        config(['app.url' => 'http://dominio-que-no-existe.invalid']);
        $id = $this->recordWithEvidence(
            'evidencias/reportes/1.png',
            base64_decode(self::PNG),
            'public',
            'http://otro-host.invalid/storage/evidencias/reportes/1.png'
        );
        $this->actAs($this->createAccount(Role::DOCENTE));

        $this->assertFileDoesNotExist(public_path('storage/evidencias/reportes/1.png'));
        $this->get("/api/central-riesgo/{$id}/evidencia")->assertOk();
    }

    public function test_un_registro_sin_evidencia_responde_404(): void
    {
        $record = $this->insertApprovedRecord($this->context);
        $this->actAs($this->createAccount(Role::DOCENTE));

        $this->getJson("/api/central-riesgo/{$record['id_cr_estudiante']}/evidencia")->assertNotFound();
    }

    public function test_si_el_archivo_falta_en_los_discos_responde_404_y_no_500(): void
    {
        $record = $this->insertApprovedRecord($this->context, [
            'evidencia_url' => 'http://localhost/storage/evidencias/reportes/falta.png',
            'evidencia_public_id' => 'evidencias/reportes/falta.png',
        ]);
        $this->actAs($this->createAccount(Role::DOCENTE));

        $this->getJson("/api/central-riesgo/{$record['id_cr_estudiante']}/evidencia")->assertNotFound();
    }

    /** @return array<string, array{0: string}> */
    public function traversalPaths(): array
    {
        return [
            'sube desde evidencias' => ['evidencias/../secreto.txt'],
            'sube varios niveles' => ['evidencias/reportes/../../secreto.txt'],
            'empieza con ..' => ['../secreto.txt'],
            'ruta absoluta' => ['/secreto.txt'],
            'fuera de evidencias' => ['secreto.txt'],
            'barras invertidas' => ['evidencias\\..\\secreto.txt'],
            'el .env' => ['evidencias/../.env'],
        ];
    }

    /**
     * @dataProvider traversalPaths
     */
    public function test_rechaza_una_ruta_guardada_que_sale_de_evidencias(string $path): void
    {
        Storage::disk('local')->put('secreto.txt', 'contenido que no debe salir');
        Storage::disk('public')->put('secreto.txt', 'contenido que no debe salir');
        $record = $this->insertApprovedRecord($this->context, ['evidencia_url' => 'x', 'evidencia_public_id' => $path]);
        $this->actAs($this->createAccount(Role::ADMINISTRADOR));

        $response = $this->getJson("/api/central-riesgo/{$record['id_cr_estudiante']}/evidencia");

        $response->assertNotFound();
        $this->assertStringNotContainsString('no debe salir', $response->getContent());
    }

    /** @return array<string, array{0: string}> */
    public function unknownIds(): array
    {
        return [
            'inexistente' => ['999999'],
            'cero' => ['0'],
            'no numerico' => ['abc'],
            'mayor al entero de la base' => ['2147483648'],
            'de once digitos' => ['12345678901'],
        ];
    }

    /**
     * @dataProvider unknownIds
     */
    public function test_un_id_inexistente_o_fuera_de_rango_responde_404(string $id): void
    {
        $this->actAs($this->createAccount(Role::DOCENTE));

        $this->getJson("/api/central-riesgo/{$id}/evidencia")->assertNotFound();
    }

    private function recordWithEvidence(string $path, string $content, string $disk, ?string $url = null): int
    {
        Storage::disk($disk)->put($path, $content);

        return $this->insertApprovedRecord($this->context, [
            'evidencia_url' => $url ?? 'http://localhost/storage/' . $path,
            'evidencia_public_id' => $path,
        ])['id_cr_estudiante'];
    }
}
