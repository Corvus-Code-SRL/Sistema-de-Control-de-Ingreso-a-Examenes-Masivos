<?php

namespace Tests\Feature\Academic;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\SeedsAcademicCatalog;
use Tests\TestCase;

class SubjectGroupsTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsAcademicCatalog;

    public function test_devuelve_los_grupos_del_par_con_los_propios_marcados(): void
    {
        $this->seedAcademicCatalog();

        $response = $this->getJson($this->groupsUrl($this->sistemasId, $this->calculoId));

        $response->assertOk()
            ->assertJsonCount(3, 'data.grupos')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.total_mios', 2)
            ->assertJsonPath('data.materia.id_materia', $this->calculoId)
            ->assertJsonPath('data.materia.id_carrera', $this->sistemasId);

        $propios = array_column($response->json('data.grupos'), 'es_mio', 'num_grupo');

        $this->assertTrue($propios['1']);
        $this->assertTrue($propios['2']);
        $this->assertFalse($propios['3']);
    }

    public function test_resuelve_por_par_y_no_por_materia_sola(): void
    {
        $this->seedAcademicCatalog();

        // Calculo II tiene tres grupos en Sistemas, pero ninguno en Informatica
        // dentro del periodo activo: consultar por materia sola daria un resultado erroneo.
        $response = $this->getJson($this->groupsUrl($this->informaticaId, $this->calculoId));

        $response->assertOk()
            ->assertJsonCount(0, 'data.grupos')
            ->assertJsonPath('meta.total', 0);
    }

    public function test_rechaza_el_par_cuya_materia_esta_inactiva(): void
    {
        $this->seedAcademicCatalog();

        $this->getJson($this->groupsUrl($this->sistemasId, $this->materiaInactivaId))
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_materia');
    }

    public function test_rechaza_el_par_inactivo_aunque_la_materia_este_activa(): void
    {
        $this->seedAcademicCatalog();

        $this->getJson($this->groupsUrl($this->informaticaId, $this->basesDatosId))
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_materia');
    }

    public function test_devuelve_404_cuando_el_par_no_existe(): void
    {
        $this->seedAcademicCatalog();

        $this->getJson($this->groupsUrl($this->informaticaId, $this->materiaInactivaId))
            ->assertNotFound();
    }

    public function test_un_docente_sin_grupos_en_el_par_no_recibe_error(): void
    {
        $this->seedAcademicCatalog();

        $response = $this->getJson($this->groupsUrl($this->sistemasId, $this->basesDatosId));

        $response->assertOk()
            ->assertJsonCount(1, 'data.grupos')
            ->assertJsonPath('meta.total_mios', 0)
            ->assertJsonPath('data.grupos.0.es_mio', false);
    }

    public function test_rechaza_identificadores_no_numericos(): void
    {
        $this->seedAcademicCatalog();

        $this->getJson('/api/carreras/abc/materias/xyz/grupos')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['id_carrera', 'id_materia']);
    }

    private function groupsUrl(int $careerId, int $subjectId): string
    {
        return "/api/carreras/{$careerId}/materias/{$subjectId}/grupos";
    }
}
