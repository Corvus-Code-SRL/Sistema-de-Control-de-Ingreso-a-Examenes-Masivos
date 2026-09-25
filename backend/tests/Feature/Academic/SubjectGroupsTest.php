<?php

namespace Tests\Feature\Academic;

use App\Models\Group;
use App\Support\RecordStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
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

    /**
     * La Policy del detalle no debe filtrarse al listado: el par se muestra completo.
     */
    public function test_el_listado_sigue_incluyendo_los_grupos_de_otros_docentes(): void
    {
        $this->seedAcademicCatalog();

        $response = $this->getJson($this->groupsUrl($this->sistemasId, $this->calculoId));

        $response->assertOk();

        $ajeno = collect($response->json('data.grupos'))->firstWhere('id_grupo', $this->grupoAjenoId);

        $this->assertNotNull($ajeno);
        $this->assertFalse($ajeno['es_mio']);
        $this->assertSame('Luis Vargas', $ajeno['docente']['nombre_completo']);
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

    /**
     * Nómina cargada es tener filas en grupo_estudiante: el estado de la fila no se
     * filtra, así que una fila escrita como INACTIVO por una versión anterior cuenta.
     */
    public function test_el_conteo_de_estudiantes_no_filtra_por_el_estado_de_la_inscripcion(): void
    {
        $this->seedAcademicCatalog();
        $this->enrollStudents($this->grupoPropioId, 2);

        $studentId = DB::table('estudiante')->insertGetId([
            'cod_sis' => '202499999',
            'ci' => null,
            'nombre' => 'ANA',
            'apellido_paterno' => 'PEREZ',
            'estado' => RecordStatus::ACTIVE,
        ], 'id_estudiante');
        DB::table('grupo_estudiante')->insert([
            'id_grupo' => $this->grupoPropioId,
            'id_estudiante' => $studentId,
            'fecha_inscripcion' => '2026-02-15',
            'estado' => RecordStatus::INACTIVE,
        ]);

        $grupos = collect(
            $this->getJson($this->groupsUrl($this->sistemasId, $this->calculoId))
                ->assertOk()
                ->json('data.grupos')
        )->keyBy('num_grupo');

        $this->assertSame(3, $grupos['1']['cantidad_estudiantes']);
    }

    public function test_cada_grupo_expone_su_docente_y_su_cantidad_de_estudiantes(): void
    {
        $this->seedAcademicCatalog();
        $this->enrollStudents($this->grupoPropioId, 3);
        $this->enrollStudents($this->grupoAjenoId, 1);

        $response = $this->getJson($this->groupsUrl($this->sistemasId, $this->calculoId))->assertOk();

        $grupos = collect($response->json('data.grupos'))->keyBy('num_grupo');

        $this->assertSame(3, $grupos['1']['cantidad_estudiantes']);
        $this->assertSame(0, $grupos['2']['cantidad_estudiantes']);
        $this->assertSame(1, $grupos['3']['cantidad_estudiantes']);

        $this->assertSame('Ana Rojas', $grupos['1']['docente']['nombre_completo']);
        $this->assertSame('Luis Vargas', $grupos['3']['docente']['nombre_completo']);
    }

    public function test_el_listado_no_lanza_una_consulta_por_cada_grupo(): void
    {
        $this->seedAcademicCatalog();

        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->getJson($this->groupsUrl($this->sistemasId, $this->calculoId))->assertOk();
        $consultasIniciales = count(DB::getQueryLog());

        foreach (['4', '5', '6', '7'] as $number) {
            Group::create([
                'id_carrera' => $this->sistemasId,
                'id_materia' => $this->calculoId,
                'num_grupo' => $number,
                'gestion' => '2026',
                'estado' => RecordStatus::ACTIVE,
                'id_usuario_docente' => $this->otroDocenteId,
                'id_periodo' => $this->periodoActivoId,
            ]);
        }

        DB::flushQueryLog();
        $this->getJson($this->groupsUrl($this->sistemasId, $this->calculoId))
            ->assertOk()
            ->assertJsonPath('meta.total', 7);
        $consultasConMasGrupos = count(DB::getQueryLog());

        DB::disableQueryLog();

        $this->assertSame($consultasIniciales, $consultasConMasGrupos);
    }

    private function groupsUrl(int $careerId, int $subjectId): string
    {
        return "/api/carreras/{$careerId}/materias/{$subjectId}/grupos";
    }
}
