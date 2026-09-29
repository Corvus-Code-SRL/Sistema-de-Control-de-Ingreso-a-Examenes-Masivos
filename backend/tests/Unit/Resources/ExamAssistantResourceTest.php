<?php

namespace Tests\Unit\Resources;

use App\Http\Resources\Exams\ExamAssistantResource;
use App\Models\Classroom;
use App\Models\ExamAssistant;
use App\Models\User;
use Illuminate\Http\Request;
use Tests\TestCase;

class ExamAssistantResourceTest extends TestCase
{
    public function test_un_auxiliar_sin_ambiente_devuelve_ambiente_null(): void
    {
        $assistant = $this->assistant(null);
        $assistant->setRelation('classroom', null);

        $data = $this->serialize($assistant);

        $this->assertNull($data['id_ambiente']);
        $this->assertNull($data['ambiente']);
        $this->assertSame('Daniela Ferrufino Soliz', $data['nombre_completo']);
        $this->assertSame('201800451', $data['cod_sis']);
    }

    public function test_un_auxiliar_con_ambiente_incluye_sus_datos(): void
    {
        $assistant = $this->assistant(7);
        $assistant->setRelation('classroom', new Classroom([
            'nro_aula' => '691A',
            'capacidad' => 60,
        ]));
        $assistant->classroom->id_ambiente = 7;

        $data = $this->serialize($assistant);

        $this->assertSame(7, $data['id_ambiente']);
        // ClassroomResource es de HU-07 y puede crecer: se comprueban solo los campos que usa HU-09.
        $this->assertSame(7, $data['ambiente']['id_ambiente']);
        $this->assertSame('691A', $data['ambiente']['nro_aula']);
        $this->assertSame(60, $data['ambiente']['capacidad']);
    }

    /** Serializa como lo haría la API, incluidos los recursos anidados. */
    private function serialize(ExamAssistant $assistant): array
    {
        return (new ExamAssistantResource($assistant))->response(new Request())->getData(true)['data'];
    }

    private function assistant(?int $classroomId): ExamAssistant
    {
        $assistant = new ExamAssistant([
            'id_examen' => 10,
            'id_usuario' => '33333333-3333-4333-8333-333333333333',
            'id_usuario_docente_habilita' => '11111111-1111-4111-8111-111111111111',
            'id_ambiente' => $classroomId,
        ]);

        $assistant->setRelation('assistant', new User([
            'nombre' => 'Daniela',
            'apellido_paterno' => 'Ferrufino',
            'apellido_materno' => 'Soliz',
            'cod_sis' => '201800451',
        ]));

        return $assistant;
    }
}