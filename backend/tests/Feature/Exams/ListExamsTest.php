<?php

namespace Tests\Feature\Exams;

use App\Models\Exam;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\SeedsExamCatalog;
use Tests\TestCase;

/**
 * GET /api/examenes — vista Programados: los exámenes del docente actual.
 */
class ListExamsTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsExamCatalog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedExamCatalog();
    }

    public function test_lista_solo_los_examenes_del_docente_actual_ordenados_por_fecha(): void
    {
        $lejano = $this->createExam(['nombre_examen' => 'Examen lejano', 'fecha' => $this->futureDate(20)]);
        $cercano = $this->createExam(['nombre_examen' => 'Examen cercano', 'fecha' => $this->futureDate(5)]);
        $this->createExam([
            'nombre_examen'      => 'Examen ajeno',
            'id_usuario_docente' => $this->otroDocenteId,
            'fecha'              => $this->futureDate(1),
        ]);

        $this->getJson('/api/examenes')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id_examen', $cercano->id_examen)
            ->assertJsonPath('data.1.id_examen', $lejano->id_examen);
    }

    public function test_expone_el_estado_de_cada_examen(): void
    {
        $this->createExam(['estado' => Exam::EN_INGRESO]);

        $this->getJson('/api/examenes')
            ->assertOk()
            ->assertJsonPath('data.0.estado', Exam::EN_INGRESO);
    }

    public function test_una_lista_vacia_responde_data_vacio(): void
    {
        $this->getJson('/api/examenes')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
