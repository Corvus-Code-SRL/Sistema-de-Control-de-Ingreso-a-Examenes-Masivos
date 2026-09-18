<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\Exam;
use App\Models\ExamType;
use App\Models\Classroom;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class ExamTest extends TestCase
{
    use DatabaseTransactions;

    protected $examType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->examType = ExamType::firstOrCreate(
            [
                'nombre'    => 'Parcial',
                'categoria' => 'MESA',
            ]
        );
    }

    /** @test */
    public function el_modelo_exam_mapea_correctamente_sus_campos()
    {
        $exam = Exam::create([
            'nombre_examen'  => 'Examen de Prueba Mapeo',
            'fecha'          => '2026-10-20',
            'hora_inicio'    => '14:00:00',
            'hora_fin'       => '15:30:00',
            'duracion'       => 90,
            'normas'         => 'Sin celulares',
            'id_tipo_examen' => $this->examType->id_tipo_examen,
        ]);

        $this->assertDatabaseHas('examen', [
            'id_examen'      => $exam->id_examen,
            'nombre_examen'  => 'Examen de Prueba Mapeo',
            'fecha'          => '2026-10-20',
            'hora_inicio'    => '14:00:00',
            'hora_fin'       => '15:30:00',
            'duracion'       => 90,
            'normas'         => 'Sin celulares',
            'id_tipo_examen' => $this->examType->id_tipo_examen,
        ]);

        $this->assertEquals('Examen de Prueba Mapeo', $exam->nombre_examen);
        $this->assertEquals(90, $exam->duracion);
        $this->assertEquals('14:00:00', $exam->hora_inicio);
    }

    /** @test */
    public function el_modelo_exam_mapea_la_relacion_con_tipo_examen()
    {
        $exam = Exam::create([
            'nombre_examen'  => 'Examen Con Tipo',
            'fecha'          => '2026-10-20',
            'hora_inicio'    => '10:00',
            'hora_fin'       => '11:30',
            'duracion'       => 90,
            'id_tipo_examen' => $this->examType->id_tipo_examen,
        ]);

        $this->assertInstanceOf(ExamType::class, $exam->examType);
        $this->assertNotEmpty($exam->examType->nombre);
    }

    /** @test */
    public function el_modelo_exam_mapea_la_relacion_muchos_a_muchos_con_ambientes()
    {
        $classroom = Classroom::firstOrCreate(
            ['nro_aula' => '691A', 'capacidad' => 100, 'ubicacion' => 'Edificio Académico', 'estado' => 'ACTIVO']
        );

        $exam = Exam::create([
            'nombre_examen'  => 'Examen Con Ambiente',
            'fecha'          => '2026-10-20',
            'hora_inicio'    => '08:00',
            'hora_fin'       => '09:30',
            'duracion'       => 90,
            'id_tipo_examen' => $this->examType->id_tipo_examen,
        ]);

        // 1. Asociar en la tabla intermedia N:N
        $exam->classrooms()->attach($classroom->id_ambiente);

        // 2. Verificar que se insertó la relación en la tabla intermedia 'examen_ambiente'
        $this->assertDatabaseHas('examen_ambiente', [
            'id_examen'   => $exam->id_examen,
            'id_ambiente' => $classroom->id_ambiente,
        ]);

        // 3. Verificar la relación N:N a través del modelo Eloquent
        $this->assertCount(1, $exam->classrooms);
        $this->assertEquals('691A', $exam->classrooms->first()->nro_aula);
    }
}
