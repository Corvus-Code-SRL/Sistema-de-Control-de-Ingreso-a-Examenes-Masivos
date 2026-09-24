<?php

namespace Tests\Feature\Academic;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\SeedsAcademicCatalog;
use Tests\TestCase;

/**
 * Un período activo sin configurar es un error de despliegue: la API falla de forma
 * temprana y limpia, y no crea grupos con un id_periodo inválido.
 */
class ActivePeriodConfigurationTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsAcademicCatalog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAcademicCatalog();
        config()->set('sciem.periodo_activo_id', null);
    }

    public function test_el_registro_falla_con_un_error_limpio_y_no_crea_el_grupo(): void
    {
        $response = $this->postJson('/api/grupos', [
            'id_carrera' => $this->sistemasId,
            'id_materia' => $this->calculoId,
            'num_grupo' => 'NUEVO',
        ]);

        $response->assertStatus(500);
        $response->assertJsonStructure(['message']);
        $this->assertStringContainsString('SCIEM_PERIODO_ACTIVO_ID', $response->json('message'));
        $this->assertNull($response->json('trace'));
        $this->assertNull($response->json('exception'));
        $this->assertDatabaseMissing('grupo', ['num_grupo' => 'NUEVO']);
    }

    public function test_el_listado_de_grupos_falla_con_un_error_limpio(): void
    {
        $response = $this->getJson(
            "/api/carreras/{$this->sistemasId}/materias/{$this->calculoId}/grupos"
        );

        $response->assertStatus(500);
        $this->assertNull($response->json('trace'));
        $this->assertStringContainsString('SCIEM_PERIODO_ACTIVO_ID', $response->json('message'));
    }

    public function test_el_listado_de_periodos_sigue_disponible_sin_periodo_activo(): void
    {
        $response = $this->getJson('/api/periodos');

        $response->assertStatus(200);
        $response->assertJsonPath('meta.id_periodo_activo', null);
        $this->assertCount(2, $response->json('data'));
    }
}
