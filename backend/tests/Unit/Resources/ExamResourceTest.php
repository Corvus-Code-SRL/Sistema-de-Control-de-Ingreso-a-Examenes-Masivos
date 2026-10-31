<?php

namespace Tests\Unit\Resources;

use App\Http\Resources\Exams\ExamResource;
use App\Models\Exam;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\SeedsExamCatalog;
use Tests\TestCase;

class ExamResourceTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsExamCatalog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedExamCatalog();
    }

    /** @test */
    public function transforma_un_examen_a_arreglo_con_sus_campos_base()
    {
        $exam = $this->createExam([
            'nombre_examen' => 'Examen Final',
            'fecha'         => '2026-11-15',
            'hora_inicio'   => '08:00',
            'duracion'      => 120,
            'normas'        => 'Obligatorio carnet de identidad.',
        ])->fresh();

        $array = (new ExamResource($exam))->toArray(request());

        $this->assertSame($exam->id_examen, $array['id_examen']);
        $this->assertSame('Examen Final', $array['nombre_examen']);
        $this->assertSame('2026-11-15', $array['fecha']);
        $this->assertSame('08:00', $array['hora_inicio']);
        $this->assertSame('10:00', $array['hora_fin']);
        $this->assertSame(120, $array['duracion']);
        $this->assertSame('Obligatorio carnet de identidad.', $array['normas']);
        $this->assertSame(Exam::PROGRAMADO, $array['estado']);
        $this->assertSame($this->sistemasId, $array['id_carrera']);
        $this->assertSame($this->calculoId, $array['id_materia']);
        $this->assertSame($this->docenteId, $array['id_usuario_docente']);
    }

    /** @test */
    public function incluye_las_relaciones_cargadas()
    {
        $exam = $this->createExam(['id_tipo_examen' => $this->examTypeId('MESA')], [$this->aulaId])
            ->load(['examType', 'subject', 'career', 'classrooms']);

        $array = json_decode((new ExamResource($exam))->toJson(), true);

        $this->assertCount(1, $array['ambientes']);
        $this->assertSame('691A', $array['ambientes'][0]['nro_aula']);
        $this->assertSame('Mesa', $array['tipo_examen']['nombre']);
        $this->assertSame('Calculo II', $array['materia']['nombre']);
        $this->assertSame('Ingenieria de Sistemas', $array['carrera']['nombre']);
    }
}
