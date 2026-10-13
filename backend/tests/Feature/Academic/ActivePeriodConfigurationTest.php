<?php

namespace Tests\Feature\Academic;

use App\Models\Period;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\SeedsAcademicCatalog;
use Tests\TestCase;

/**
 * Sin ningún período vigente (gestion_periodo.vigente) es un error de despliegue: la API falla de
 * forma temprana y limpia, y no crea grupos sin período.
 */
class ActivePeriodConfigurationTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsAcademicCatalog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAcademicCatalog();
        Period::query()->update(['vigente' => false]);
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
        $this->assertStringContainsString('período académico vigente', $response->json('message'));
        $this->assertStringNotContainsString('SCIEM_PERIODO_ACTIVO_ID', $response->json('message'));
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
        $this->assertStringContainsString('período académico vigente', $response->json('message'));
    }

    public function test_el_listado_de_periodos_sigue_disponible_sin_periodo_activo(): void
    {
        $response = $this->getJson('/api/periodos');

        $response->assertStatus(200);
        $response->assertJsonPath('meta.id_periodo_activo', null);
        $this->assertCount(2, $response->json('data'));

        foreach ($response->json('data') as $period) {
            $this->assertFalse($period['vigente']);
        }
    }

    public function test_fijar_un_periodo_como_vigente_lo_habilita_y_solo_puede_haber_uno(): void
    {
        $this->assertTrue(Period::query()->whereKey($this->periodoActivoId)->update(['vigente' => true]) === 1);

        $this->getJson('/api/periodos')->assertOk()
            ->assertJsonPath('meta.id_periodo_activo', $this->periodoActivoId);

        $this->expectException(\Illuminate\Database\QueryException::class);
        Period::query()->whereKey($this->periodoAnteriorId)->update(['vigente' => true]);
    }
}
