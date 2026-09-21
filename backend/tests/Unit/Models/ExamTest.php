<?php

namespace Tests\Unit\Models;

use App\Models\Career;
use App\Models\Classroom;
use App\Models\Exam;
use App\Models\ExamType;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsExamCatalog;
use Tests\TestCase;

class ExamTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsExamCatalog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedExamCatalog();
    }

    /** @test */
    public function el_modelo_exam_mapea_correctamente_sus_campos()
    {
        $exam = $this->createExam([
            'nombre_examen' => 'Examen de Prueba Mapeo',
            'fecha'         => '2026-10-20',
            'hora_inicio'   => '14:00',
            'duracion'      => 90,
            'normas'        => 'Sin celulares',
        ]);

        $this->assertDatabaseHas('examen', [
            'id_examen'          => $exam->id_examen,
            'nombre_examen'      => 'Examen de Prueba Mapeo',
            'fecha'              => '2026-10-20',
            'hora_inicio'        => '14:00:00',
            'hora_fin'           => '15:30:00',
            'duracion'           => 90,
            'normas'             => 'Sin celulares',
            'id_carrera'         => $this->sistemasId,
            'id_materia'         => $this->calculoId,
            'id_usuario_docente' => $this->docenteId,
            'estado'             => Exam::PROGRAMADO,
        ]);

        $this->assertSame(90, $exam->duracion);
        $this->assertSame('2026-10-20', $exam->fecha->toDateString());
    }

    /** @test */
    public function un_examen_nuevo_queda_programado_por_defecto()
    {
        $examId = DB::table('examen')->insertGetId([
            'nombre_examen'      => 'Sin estado explícito',
            'fecha'              => '2026-10-20',
            'hora_inicio'        => '08:00',
            'id_tipo_examen'     => $this->examTypeId('REGULAR'),
            'id_usuario_docente' => $this->docenteId,
        ], 'id_examen');

        $this->assertSame(Exam::PROGRAMADO, Exam::findOrFail($examId)->estado);
    }

    /** @test */
    public function el_modelo_exam_mapea_sus_relaciones()
    {
        $exam = $this->createExam([], [$this->aulaId]);

        $this->assertInstanceOf(ExamType::class, $exam->examType);
        $this->assertInstanceOf(Subject::class, $exam->subject);
        $this->assertInstanceOf(Career::class, $exam->career);
        $this->assertInstanceOf(User::class, $exam->teacher);
        $this->assertSame($this->docenteId, $exam->teacher->id_usuario);
        $this->assertCount(1, $exam->classrooms);
        $this->assertInstanceOf(Classroom::class, $exam->classrooms->first());
        $this->assertSame('691A', $exam->classrooms->first()->nro_aula);
    }

    /** @test */
    public function el_par_materia_carrera_debe_existir()
    {
        $this->expectException(QueryException::class);

        // La materia existe, pero no está registrada en Informática.
        $this->createExam([
            'id_carrera' => $this->informaticaId,
            'id_materia' => $this->materiaInactivaId,
        ]);
    }

    /** @test */
    public function el_par_materia_carrera_va_completo_o_no_va()
    {
        $this->expectException(QueryException::class);

        $this->createExam(['id_carrera' => null]);
    }

    /** @test */
    public function un_examen_puede_cruzar_la_medianoche()
    {
        $exam = $this->createExam(['hora_inicio' => '23:00', 'duracion' => 120]);

        $this->assertDatabaseHas('examen', [
            'id_examen'   => $exam->id_examen,
            'hora_inicio' => '23:00:00',
            'hora_fin'    => '01:00:00',
        ]);
    }

    /** @test */
    public function la_hora_de_fin_debe_corresponder_a_la_duracion()
    {
        $this->expectException(QueryException::class);

        $this->createExam(['hora_inicio' => '08:00', 'duracion' => 90, 'hora_fin' => '12:00']);
    }
}
