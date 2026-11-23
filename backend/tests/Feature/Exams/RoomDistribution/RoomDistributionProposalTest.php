<?php

namespace Tests\Feature\Exams\RoomDistribution;

use App\Models\Student;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsRoomDistribution;
use Tests\TestCase;

class RoomDistributionProposalTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsRoomDistribution;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedExamCatalog();
    }

    public function test_cincuenta_y_cinco_se_reparten_en_28_y_27_sin_persistir_ni_auditar(): void
    {
        $exam = $this->distributionExam(55);
        $before = DB::table('log')->count();
        $response = $this->postJson($this->distributionUrl($exam) . '/propuesta')->assertOk()
            ->assertJsonPath('data.sin_asignar', 0)->assertJsonCount(55, 'data.estudiantes');
        $students = $response->json('data.estudiantes');

        $this->assertSame(array_fill(0, 28, $this->aulaId), array_column(array_slice($students, 0, 28), 'id_ambiente'));
        $this->assertSame(
            array_fill(0, 27, $this->otraAulaId),
            array_column(array_slice($students, 28), 'id_ambiente')
        );
        $this->assertSame(0, DB::table('distribucion_ambiente')->count());
        $this->assertSame($before, DB::table('log')->count());
        $this->assertSame(
            array_column($students, 'id_estudiante'),
            array_column($this->getJson($this->distributionUrl($exam))->json('data.estudiantes'), 'id_estudiante')
        );
    }

    public function test_el_orden_es_espanol_y_desempata_por_sis(): void
    {
        $exam = $this->distributionExam(8);
        $ids = $this->distributionStudentIds($exam);
        $names = ['Ochoa', 'Ñuñez', 'Nzz', 'Núñez', 'Navarro', 'Muñoz', 'Álvarez', 'ALVAREZ'];

        foreach ($ids as $index => $id) {
            Student::query()->whereKey($id)->update(['apellido_paterno' => $names[$index], 'nombre' => 'Ana']);
        }

        $response = $this->postJson($this->distributionUrl($exam) . '/propuesta')->assertOk();
        $expected = [$ids[6], $ids[7], $ids[5], $ids[4], $ids[3], $ids[2], $ids[1], $ids[0]];

        $this->assertSame($expected, array_column($response->json('data.estudiantes'), 'id_estudiante'));
        $this->assertSame('Álvarez Ana', $response->json('data.estudiantes.0.nombre_completo'));
    }

    public function test_capacidades_desiguales_reparten_partes_iguales(): void
    {
        $exam = $this->distributionExam(60, [40, 20, 20]);
        $response = $this->postJson($this->distributionUrl($exam) . '/propuesta')->assertOk();

        $this->assertSame([20, 20, 20], array_values(array_count_values(
            array_column($response->json('data.estudiantes'), 'id_ambiente')
        )));
    }

    public function test_capacidad_insuficiente_deja_excedentes_sin_asignar_y_no_supera_aforo(): void
    {
        $exam = $this->distributionExam(7, [2, 3]);
        $response = $this->postJson($this->distributionUrl($exam) . '/propuesta')->assertOk()
            ->assertJsonPath('data.sin_asignar', 2);
        $rooms = array_column($response->json('data.estudiantes'), 'id_ambiente');

        $this->assertSame([$this->aulaId, $this->aulaId, $this->otraAulaId, $this->otraAulaId,
            $this->otraAulaId, null, null], $rooms);
    }

    public function test_no_habilitados_quedan_fuera_de_la_propuesta(): void
    {
        $exam = $this->distributionExam(3);
        $studentId = $this->distributionStudentIds($exam)[0];
        $this->setDistributionEligibility($exam, $studentId, 'NO_HABILITADO');

        $response = $this->postJson($this->distributionUrl($exam) . '/propuesta')->assertOk()
            ->assertJsonCount(2, 'data.estudiantes');

        $this->assertNotContains($studentId, array_column($response->json('data.estudiantes'), 'id_estudiante'));
    }

    public function test_cero_habilitados_devuelve_una_propuesta_vacia(): void
    {
        $exam = $this->distributionExam(0);

        $this->postJson($this->distributionUrl($exam) . '/propuesta')->assertOk()
            ->assertExactJson(['data' => ['estudiantes' => [], 'sin_asignar' => 0]]);
    }
}
