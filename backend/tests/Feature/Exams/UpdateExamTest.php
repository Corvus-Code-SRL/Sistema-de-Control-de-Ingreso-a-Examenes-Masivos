<?php

namespace Tests\Feature\Exams;

use App\Models\Exam;
use App\Models\ExamAssistant;
use App\Support\RecordStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsExamCatalog;
use Tests\TestCase;

/**
 * HU-24 — criterios 10 y 11: la información general se edita solo mientras el
 * examen está PROGRAMADO.
 */
class UpdateExamTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsExamCatalog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedExamCatalog();
    }

    private function url(Exam $exam): string
    {
        return "/api/examenes/{$exam->id_examen}";
    }

    public function test_criterio_10_edita_la_informacion_general_mientras_esta_programado(): void
    {
        $exam = $this->createExam(['nombre_examen' => 'Primer parcial'], [$this->aulaId]);
        $newDate = $this->futureDate(10);

        $this->putJson($this->url($exam), $this->validPayload([
            'nombre_examen' => 'Parcial reprogramado',
            'id_carrera'    => $this->informaticaId,
            'id_materia'    => $this->calculoId,
            'categoria'     => 'MESA',
            'fecha'         => $newDate,
            'hora_inicio'   => '10:15',
            'duracion'      => 120,
            'ambientes'     => [$this->otraAulaId],
            'normas'        => 'Solo calculadora.',
        ]))
            ->assertOk()
            ->assertJsonPath('mensaje', 'Examen actualizado correctamente.')
            ->assertJsonPath('data.estado', Exam::PROGRAMADO)
            ->assertJsonPath('data.hora_fin', '12:15')
            ->assertJsonPath('data.carrera.nombre', 'Ingenieria Informatica');

        $this->assertDatabaseHas('examen', [
            'id_examen'          => $exam->id_examen,
            'nombre_examen'      => 'Parcial reprogramado',
            'id_carrera'         => $this->informaticaId,
            'id_materia'         => $this->calculoId,
            'id_tipo_examen'     => $this->examTypeId('MESA'),
            'fecha'              => $newDate,
            'duracion'           => 120,
            'normas'             => 'Solo calculadora.',
            'id_usuario_docente' => $this->docenteId,
            'estado'             => Exam::PROGRAMADO,
        ]);
        $this->assertSame(
            [$this->otraAulaId],
            $exam->classrooms()->pluck('ambiente.id_ambiente')->all()
        );
    }

    /**
     * Descubierto en HU-09: quitar del examen un ambiente que ya tenía un auxiliar
     * asignado violaba la FK compuesta examen_auxiliar → examen_ambiente (500).
     * El auxiliar de ese ambiente queda sin ambiente y los demás no cambian.
     */
    public function test_quitar_un_ambiente_deja_sin_ambiente_solo_a_sus_auxiliares(): void
    {
        $exam = $this->createExam([], [$this->aulaId, $this->otraAulaId]);

        $releasedId = '00000000-0000-4000-8000-000000000091';
        $keptId = '00000000-0000-4000-8000-000000000092';

        DB::table('usuario')->insert([
            [
                'id_usuario'       => $releasedId,
                'nombre'           => 'Auxiliar',
                'apellido_paterno' => 'Liberado',
                'apellido_materno' => null,
                'correo'           => 'auxiliar.liberado@umss.edu',
                'contrasenia'      => 'x',
                'cod_sis'          => '209900091',
                'estado'           => RecordStatus::ACTIVE,
            ],
            [
                'id_usuario'       => $keptId,
                'nombre'           => 'Auxiliar',
                'apellido_paterno' => 'Conservado',
                'apellido_materno' => null,
                'correo'           => 'auxiliar.conservado@umss.edu',
                'contrasenia'      => 'x',
                'cod_sis'          => '209900092',
                'estado'           => RecordStatus::ACTIVE,
            ],
        ]);

        foreach ([$releasedId => $this->aulaId, $keptId => $this->otraAulaId] as $userId => $classroomId) {
            ExamAssistant::create([
                'id_examen'                   => $exam->id_examen,
                'id_usuario'                  => $userId,
                'id_usuario_docente_habilita' => $exam->id_usuario_docente,
                'id_ambiente'                 => $classroomId,
            ]);
        }

        $this->putJson($this->url($exam), $this->validPayload([
            'fecha'     => $this->futureDate(10),
            'ambientes' => [$this->otraAulaId],
        ]))->assertOk();

        $this->assertDatabaseHas('examen_auxiliar', [
            'id_examen'   => $exam->id_examen,
            'id_usuario'  => $releasedId,
            'id_ambiente' => null,
        ]);
        $this->assertDatabaseHas('examen_auxiliar', [
            'id_examen'   => $exam->id_examen,
            'id_usuario'  => $keptId,
            'id_ambiente' => $this->otraAulaId,
        ]);
        $this->assertDatabaseMissing('examen_ambiente', [
            'id_examen'   => $exam->id_examen,
            'id_ambiente' => $this->aulaId,
        ]);
    }

    public function test_la_edicion_no_se_advierte_contra_el_propio_examen(): void
    {
        $exam = $this->createExam(['nombre_examen' => 'Primer parcial'], [$this->aulaId]);

        // Mismo nombre, fecha, horario y ambiente que ya tiene: no es un duplicado de sí mismo.
        $this->putJson($this->url($exam), $this->validPayload([
            'fecha'       => $exam->fecha->toDateString(),
            'normas'      => 'Normas corregidas.',
        ]))->assertOk();
    }

    public function test_la_edicion_advierte_superposicion_con_otros_examenes(): void
    {
        $this->createExam(['nombre_examen' => 'Otro examen', 'hora_inicio' => '10:00'], [$this->otraAulaId]);
        $exam = $this->createExam(['nombre_examen' => 'Primer parcial'], [$this->aulaId]);

        $this->putJson($this->url($exam), $this->validPayload(['hora_inicio' => '10:30']))
            ->assertStatus(409)
            ->assertJsonStructure(['errors' => ['superposicion_horario']]);

        $this->assertDatabaseHas('examen', ['id_examen' => $exam->id_examen, 'hora_inicio' => '08:00']);
    }

    public function test_la_edicion_valida_los_mismos_datos_que_la_creacion(): void
    {
        $exam = $this->createExam([], [$this->aulaId]);

        $this->putJson($this->url($exam), $this->validPayload([
            'duracion'   => 0,
            'id_carrera' => $this->informaticaId,
            'id_materia' => $this->basesDatosId,
        ]))->assertUnprocessable()->assertJsonValidationErrors('duracion');

        // Par INACTIVO (regla de HU-16).
        $this->putJson($this->url($exam), $this->validPayload([
            'id_carrera' => $this->informaticaId,
            'id_materia' => $this->basesDatosId,
        ]))->assertUnprocessable()->assertJsonValidationErrors('id_materia');

        $this->assertDatabaseHas('examen', ['id_examen' => $exam->id_examen, 'id_carrera' => $this->sistemasId]);
    }

    public function test_criterio_11_desde_en_ingreso_la_informacion_general_queda_bloqueada(): void
    {
        foreach ([Exam::EN_INGRESO, Exam::EN_CURSO, Exam::FINALIZADO] as $status) {
            $exam = $this->createExam(['nombre_examen' => "Examen {$status}", 'estado' => $status], [$this->aulaId]);

            $this->putJson($this->url($exam), $this->validPayload([
                'nombre_examen' => 'Cambiado',
                'fecha'         => $this->futureDate(20),
                'hora_inicio'   => '15:00',
                'duracion'      => 45,
                'id_carrera'    => $this->informaticaId,
            ]))
                ->assertStatus(409)
                ->assertJsonPath(
                    'message',
                    'El control de ingreso del examen ya se inició: su información general no puede modificarse.'
                );

            $this->assertDatabaseHas('examen', [
                'id_examen'     => $exam->id_examen,
                'nombre_examen' => "Examen {$status}",
                'fecha'         => $exam->fecha->toDateString(),
                'hora_inicio'   => '08:00:00',
                'duracion'      => 90,
                'id_carrera'    => $this->sistemasId,
                'id_materia'    => $this->calculoId,
                'estado'        => $status,
            ]);
        }
    }

    public function test_un_examen_cancelado_no_se_puede_editar(): void
    {
        $exam = $this->createExam(['estado' => Exam::CANCELADO], [$this->aulaId]);

        $this->putJson($this->url($exam), $this->validPayload(['nombre_examen' => 'Cambiado']))
            ->assertStatus(409)
            ->assertJsonPath('message', 'El examen está cancelado y ya no puede modificarse.');
    }

    public function test_solo_el_docente_creador_puede_editar(): void
    {
        $exam = $this->createExam(['id_usuario_docente' => $this->otroDocenteId], [$this->aulaId]);

        $this->putJson($this->url($exam), $this->validPayload(['nombre_examen' => 'Cambiado']))
            ->assertForbidden();

        $this->assertDatabaseHas('examen', ['id_examen' => $exam->id_examen, 'nombre_examen' => 'Examen existente']);
    }

    public function test_responde_404_si_el_examen_no_existe(): void
    {
        $this->putJson('/api/examenes/999999', $this->validPayload())->assertNotFound();
        $this->putJson('/api/examenes/abc', $this->validPayload())->assertNotFound();
    }
}
