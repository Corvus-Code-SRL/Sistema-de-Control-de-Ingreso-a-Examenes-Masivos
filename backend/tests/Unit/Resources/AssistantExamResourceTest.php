<?php

namespace Tests\Unit\Resources;

use App\Http\Resources\Exams\AssistantExamResource;
use App\Models\Classroom;
use App\Models\Exam;
use App\Models\ExamAssistant;
use App\Models\Subject;
use Illuminate\Http\Request;
use Tests\TestCase;

class AssistantExamResourceTest extends TestCase
{
    public function test_un_examen_con_ambiente_incluye_sus_datos_y_las_horas_en_hh_mm(): void
    {
        $assignment = $this->assignment([
            'fecha' => '2026-10-06',
            'hora_inicio' => '08:00:00',
            'hora_fin' => '09:30:00',
        ]);
        $assignment->exam->setRelation('subject', new Subject(['nombre' => 'Bases de Datos I']));
        $classroom = new Classroom(['nro_aula' => '691A', 'capacidad' => 60]);
        $classroom->id_ambiente = 7;
        $assignment->setRelation('classroom', $classroom);

        $data = $this->serialize($assignment);

        $this->assertSame(10, $data['id_examen']);
        $this->assertSame('Primer parcial', $data['nombre_examen']);
        $this->assertSame('2026-10-06', $data['fecha']);
        $this->assertSame('08:00', $data['hora_inicio']);
        $this->assertSame('09:30', $data['hora_fin']);
        $this->assertSame(Exam::PROGRAMADO, $data['estado']);
        $this->assertSame('Bases de Datos I', $data['materia']);
        // ClassroomResource es de HU-07 y puede crecer: se comprueban solo los campos que usa HU-09.
        $this->assertSame(7, $data['ambiente']['id_ambiente']);
        $this->assertSame('691A', $data['ambiente']['nro_aula']);
    }

    public function test_los_datos_que_faltan_llegan_como_null(): void
    {
        $assignment = $this->assignment(['fecha' => null, 'hora_inicio' => null, 'hora_fin' => null]);
        $assignment->exam->setRelation('subject', null);
        $assignment->setRelation('classroom', null);

        $data = $this->serialize($assignment);

        $this->assertNull($data['fecha']);
        $this->assertNull($data['hora_inicio']);
        $this->assertNull($data['hora_fin']);
        $this->assertNull($data['materia']);
        $this->assertNull($data['ambiente']);
    }

    /** Serializa como lo haría la API, incluidos los recursos anidados. */
    private function serialize(ExamAssistant $assignment): array
    {
        return (new AssistantExamResource($assignment))->response(new Request())->getData(true)['data'];
    }

    private function assignment(array $examAttributes): ExamAssistant
    {
        $exam = new Exam(array_merge([
            'nombre_examen' => 'Primer parcial',
            'estado' => Exam::PROGRAMADO,
        ], $examAttributes));
        $exam->id_examen = 10;

        $assignment = new ExamAssistant([
            'id_examen' => 10,
            'id_usuario' => '33333333-3333-4333-8333-333333333333',
        ]);
        $assignment->setRelation('exam', $exam);

        return $assignment;
    }
}
