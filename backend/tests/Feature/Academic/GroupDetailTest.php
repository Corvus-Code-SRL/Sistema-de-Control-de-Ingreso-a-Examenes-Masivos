<?php

namespace Tests\Feature\Academic;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\SeedsAcademicCatalog;
use Tests\TestCase;

class GroupDetailTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsAcademicCatalog;

    public function test_ubica_el_grupo_con_su_par_periodo_y_docente(): void
    {
        $this->seedAcademicCatalog();
        $this->enrollStudents($this->grupoPropioId, 4);

        $this->getJson($this->groupUrl($this->grupoPropioId))
            ->assertOk()
            ->assertJsonPath('data.grupo.id_grupo', $this->grupoPropioId)
            ->assertJsonPath('data.grupo.num_grupo', '1')
            ->assertJsonPath('data.grupo.es_mio', true)
            ->assertJsonPath('data.grupo.cantidad_estudiantes', 4)
            ->assertJsonPath('data.grupo.docente.nombre_completo', 'Ana Rojas')
            ->assertJsonPath('data.grupo.periodo.id_periodo', $this->periodoActivoId)
            ->assertJsonPath('data.materia.id_materia', $this->calculoId)
            ->assertJsonPath('data.materia.id_carrera', $this->sistemasId)
            ->assertJsonPath('data.materia.carrera.codigo', 'SIS')
            ->assertJsonPath('meta.es_periodo_activo', true)
            ->assertJsonStructure([
                'data' => [
                    'grupo' => [
                        'id_grupo',
                        'num_grupo',
                        'gestion',
                        'activo',
                        'es_mio',
                        'cantidad_estudiantes',
                        'docente' => ['nombre_completo'],
                        'periodo' => ['id_periodo', 'nombre_periodo', 'gestion'],
                    ],
                    'materia' => ['id_materia', 'id_carrera', 'nombre', 'codigo', 'carrera'],
                ],
                'meta' => ['id_periodo_activo', 'es_periodo_activo'],
            ]);
    }

    public function test_rechaza_con_403_el_grupo_de_otro_docente(): void
    {
        $this->seedAcademicCatalog();
        $this->enrollStudents($this->grupoAjenoId, 3);

        $response = $this->getJson($this->groupUrl($this->grupoAjenoId))
            ->assertForbidden()
            ->assertJsonPath('message', 'Solo el docente que dicta el grupo puede ver su detalle.')
            ->assertDontSee('Luis Vargas');

        $this->assertArrayNotHasKey('data', $response->json());
    }

    public function test_la_propiedad_se_resuelve_con_el_docente_de_configuracion(): void
    {
        $this->seedAcademicCatalog();

        config()->set('sciem.docente_fijo_id', $this->otroDocenteId);

        $this->getJson($this->groupUrl($this->grupoAjenoId))
            ->assertOk()
            ->assertJsonPath('data.grupo.es_mio', true)
            ->assertJsonPath('data.grupo.docente.nombre_completo', 'Luis Vargas');

        $this->getJson($this->groupUrl($this->grupoPropioId))
            ->assertForbidden();
    }

    public function test_indica_cuando_el_grupo_no_es_del_periodo_activo(): void
    {
        $this->seedAcademicCatalog();

        // Calculo II de Informatica: mismo nombre de materia, otra carrera y otro periodo.
        $this->getJson($this->groupUrl($this->grupoPeriodoAnteriorId))
            ->assertOk()
            ->assertJsonPath('data.materia.id_carrera', $this->informaticaId)
            ->assertJsonPath('data.grupo.periodo.nombre_periodo', '2025-2')
            ->assertJsonPath('meta.es_periodo_activo', false);
    }

    public function test_rechaza_el_grupo_cuyo_par_esta_inactivo(): void
    {
        $this->seedAcademicCatalog();

        // El grupo lo dicta el otro docente: se consulta como él para llegar a la regla del par.
        config()->set('sciem.docente_fijo_id', $this->otroDocenteId);

        $this->getJson($this->groupUrl($this->grupoParInactivoId))
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_materia');
    }

    public function test_devuelve_404_cuando_el_grupo_no_existe(): void
    {
        $this->seedAcademicCatalog();

        $this->getJson($this->groupUrl(999999))
            ->assertNotFound()
            ->assertJsonPath('message', 'No existe el grupo indicado.');
    }

    public function test_rechaza_identificador_no_numerico(): void
    {
        $this->seedAcademicCatalog();

        $this->getJson('/api/grupos/abc')
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_grupo');
    }

    private function groupUrl(int $groupId): string
    {
        return "/api/grupos/{$groupId}";
    }
}
