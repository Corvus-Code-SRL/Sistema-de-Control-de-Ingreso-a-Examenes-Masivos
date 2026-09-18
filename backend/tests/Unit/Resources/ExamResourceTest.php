<?php

namespace Tests\Unit\Resources;

use App\Http\Resources\Exams\ExamResource;
use App\Models\Classroom;
use App\Models\Exam;
use App\Models\ExamType;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ExamResourceTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function transforma_un_examen_a_arreglo_con_sus_campos_base()
    {
        $examType = ExamType::firstOrCreate([
            'nombre'    => 'Parcial',
            'categoria' => 'REGULAR',
        ]);

        $exam = Exam::create([
            'nombre_examen'  => 'Examen Final',
            'fecha'          => '2026-11-15',
            'hora_inicio'    => '08:00',
            'hora_fin'       => '10:00',
            'duracion'       => 120,
            'normas'         => 'Obligatorio carnet de identidad.',
            'id_tipo_examen' => $examType->id_tipo_examen,
        ]);

        $resource = new ExamResource($exam);
        $array = $resource->toArray(request());

        $this->assertEquals($exam->id_examen, $array['id_examen']);
        $this->assertEquals('Examen Final', $array['nombre_examen']);
        $this->assertEquals('2026-11-15', $array['fecha']);
        $this->assertEquals('08:00', $array['hora_inicio']);
        $this->assertEquals('10:00', $array['hora_fin']);
        $this->assertEquals(120, $array['duracion']);
        $this->assertEquals('Obligatorio carnet de identidad.', $array['normas']);
        $this->assertEquals($examType->id_tipo_examen, $array['id_tipo_examen']);
    }

    /** @test */
    public function incluye_las_relaciones_cargadas_correctamente()
    {
        $examType = ExamType::firstOrCreate([
            'nombre'    => 'Mesa',
            'categoria' => 'MESA',
        ]);

        $classroom = Classroom::firstOrCreate([
            'nro_aula'  => 'Aula 690',
            'capacidad' => 80,
            'ubicacion' => 'Edificio Nuevo',
            'estado'    => 'ACTIVO',
        ]);

        $exam = Exam::create([
            'nombre_examen'  => 'Examen Con Relaciones',
            'fecha'          => '2026-12-01',
            'hora_inicio'    => '14:00',
            'hora_fin'       => '16:00',
            'duracion'       => 120,
            'id_tipo_examen' => $examType->id_tipo_examen,
        ]);

        $exam->classrooms()->attach($classroom->id_ambiente);

        $exam->load(['ambiente', 'tipo_examen']);

        $resource = new ExamResource($exam);
        $array = $resource->toArray(request());

        $this->assertArrayHasKey('ambientes', $array);
        $this->assertCount(1, $array['ambientes']);
        $this->assertEquals('Aula 690', $array['ambientes'][0]['nro_aula']);

        $this->assertArrayHasKey('tipo_examen', $array);
        $this->assertEquals('Mesa', $array['tipo_examen']['nombre']);
    }
}
