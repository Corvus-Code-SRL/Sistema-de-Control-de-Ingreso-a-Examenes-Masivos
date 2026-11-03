<?php

namespace Tests\Feature\EntryControl;

use App\Exceptions\EntryControl\LateEntryException;
use App\Models\Exam;
use App\Services\EntryControl\LateEntryQueryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsLateEntryExam;
use Tests\TestCase;

/**
 * HU-037: GET .../retrasos/buscar sugiere estudiantes de la nómina mientras el examen está
 * EN_CURSO, con las reglas y la forma de la búsqueda de HU-032 pero leyendo PostgreSQL.
 */
class LateEntrySearchTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsLateEntryExam;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedLateEntryExam();
        $this->actAsTeacher($this->docenteId);
    }

    /** @param array<string, string> $query */
    private function search(array $query)
    {
        return $this->getJson($this->lateEntryUrl('buscar') . '?' . http_build_query($query));
    }

    public function test_busca_por_nombre_sin_distinguir_tildes_ni_mayusculas(): void
    {
        DB::table('estudiante')->where('id_estudiante', $this->secondStudentId)
            ->update(['nombre' => 'José', 'apellido_paterno' => 'Pérez', 'apellido_materno' => 'Núñez']);

        $this->search(['nombre' => 'jose perez'])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertExactJson(['data' => [[
                'id_estudiante' => $this->secondStudentId,
                'cod_sis' => $this->studentColumn($this->secondStudentId, 'cod_sis'),
                'nombre_completo' => 'José Pérez Núñez',
            ]]]);
    }

    public function test_busca_por_prefijo_de_sis_y_de_ci_y_solo_devuelve_el_ci_al_buscar_por_ci(): void
    {
        $sis = $this->studentColumn($this->firstStudentId, 'cod_sis');
        $ci = $this->studentColumn($this->firstStudentId, 'ci');
        DB::table('estudiante')->where('id_estudiante', $this->secondStudentId)->update(['ci' => '7777777']);

        $bySis = $this->search(['sis' => substr($sis, 0, 8)])->assertOk();
        $this->assertSame(
            [$this->firstStudentId, $this->secondStudentId],
            array_column($bySis->json('data'), 'id_estudiante')
        );
        $this->assertArrayNotHasKey('ci', $bySis->json('data.0'));

        $this->search(['ci' => $ci])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_estudiante', $this->firstStudentId)
            ->assertJsonPath('data.0.ci', $ci);
    }

    public function test_devuelve_como_maximo_cinco_y_excluye_a_estudiantes_de_otro_examen(): void
    {
        $this->enrollStudents($this->grupoPropioId, 5);
        $this->enrollStudents($this->grupoAjenoId, 1);
        $outsider = (int) DB::table('grupo_estudiante')->where('id_grupo', $this->grupoAjenoId)->value('id_estudiante');

        $matches = $this->search(['nombre' => 'prueba'])->assertOk()->json('data');

        $this->assertCount(LateEntryQueryService::SEARCH_LIMIT, $matches);
        $this->assertNotContains($outsider, array_column($matches, 'id_estudiante'));
        $this->search(['sis' => substr($this->studentColumn($outsider, 'cod_sis'), 0, 9)])
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_el_auxiliar_habilitado_tambien_busca(): void
    {
        $this->actAsUserId($this->assistantBId);

        $this->search(['nombre' => 'estudiante'])->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_un_examen_que_no_esta_en_curso_responde_409(): void
    {
        $this->setLateEntryExamState(Exam::FINALIZADO);

        $this->search(['nombre' => 'estudiante'])
            ->assertStatus(409)
            ->assertJsonPath('veredicto', LateEntryException::EXAM_NOT_IN_PROGRESS);
    }

    public function test_exige_una_cuenta_que_controle_el_examen(): void
    {
        $this->actAsTeacher($this->otroDocenteId);
        $this->search(['nombre' => 'estudiante'])->assertForbidden();

        DB::table('examen_auxiliar')->where('id_usuario', $this->assistantAId)->delete();
        $this->actAsUserId($this->assistantAId);
        $this->search(['nombre' => 'estudiante'])->assertForbidden();

        $this->actAsGuest();
        $this->search(['nombre' => 'estudiante'])->assertUnauthorized();
    }

    public function test_valida_la_busqueda_como_hu_032(): void
    {
        $this->search([])->assertStatus(422)->assertJsonValidationErrors(['nombre', 'sis', 'ci']);
        $this->search(['sis' => '12'])
            ->assertStatus(422)
            ->assertJsonPath('errors.sis.0', 'Ingrese entre 3 y 9 dígitos del código SIS.');
        $this->search(['nombre' => 'ana', 'ci' => '1234'])->assertStatus(422)->assertJsonValidationErrors(['nombre']);
    }
}
