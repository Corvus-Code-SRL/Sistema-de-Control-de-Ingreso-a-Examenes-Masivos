<?php

namespace Tests\Feature\Exams\CollaboratorInvitations;

use App\Models\Exam;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\AssignsIncidentRole;
use Tests\Concerns\SeedsCollaboratorInvitations;
use Tests\Concerns\SeedsExamCatalog;
use Tests\TestCase;

/** HU-026, criterios 1 y 2: el desplegable ofrece solo los exámenes PROGRAMADOS propios. */
class InvitableExamsTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsExamCatalog;
    use AssignsIncidentRole;
    use SeedsCollaboratorInvitations;

    private const URL = '/api/docente/invitaciones/examenes';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedExamCatalog();
    }

    public function test_lista_el_examen_con_nombre_materia_fecha_hora_y_ambientes(): void
    {
        $this->createExam([
            'nombre_examen' => 'Parcial de Cálculo',
            'fecha' => '2030-03-05',
            'hora_inicio' => '09:30',
            'duracion' => 90,
        ], [$this->aulaId, $this->otraAulaId]);

        $response = $this->getJson(self::URL)->assertOk();

        $response->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nombre_examen', 'Parcial de Cálculo')
            ->assertJsonPath('data.0.fecha', '2030-03-05')
            ->assertJsonPath('data.0.hora_inicio', '09:30')
            ->assertJsonPath('data.0.hora_fin', '11:00')
            ->assertJsonPath('data.0.materia.nombre', 'Calculo II')
            ->assertJsonPath('data.0.carrera.nombre', 'Ingenieria de Sistemas');

        $this->assertEqualsCanonicalizing(
            ['691A', '692B'],
            collect($response->json('data.0.ambientes'))->pluck('nro_aula')->all()
        );
    }

    public function test_solo_ofrece_examenes_programados_propios(): void
    {
        $programmed = $this->createExam(['nombre_examen' => 'Programado']);
        $this->createExam(array_merge(['nombre_examen' => 'Ingreso abierto'], $this->entryOpen()));
        $this->createExam(['nombre_examen' => 'En curso', 'estado' => Exam::EN_CURSO]);
        $this->createExam(['nombre_examen' => 'Finalizado', 'estado' => Exam::FINALIZADO]);
        $this->createExam(['nombre_examen' => 'Cancelado', 'estado' => Exam::CANCELADO]);
        $this->createExam(['nombre_examen' => 'Ajeno', 'id_usuario_docente' => $this->otroDocenteId]);

        $this->getJson(self::URL)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_examen', $programmed->id_examen);
    }

    public function test_los_ordena_del_mas_proximo_al_mas_lejano(): void
    {
        $this->createExam(['nombre_examen' => 'Tarde', 'fecha' => '2030-05-01']);
        $this->createExam(['nombre_examen' => 'Temprano', 'fecha' => '2030-04-01']);

        $names = $this->getJson(self::URL)->assertOk()->json('data.*.nombre_examen');

        $this->assertSame(['Temprano', 'Tarde'], $names);
    }

    public function test_sin_examenes_programados_devuelve_una_lista_vacia(): void
    {
        $this->createExam($this->examStateAttributes('INGRESO_ABIERTO'));

        $this->getJson(self::URL)->assertOk()->assertExactJson(['data' => []]);
    }

    public function test_un_examen_que_abre_el_control_de_ingreso_deja_de_aparecer(): void
    {
        $exam = $this->createExam();
        $this->getJson(self::URL)->assertOk()->assertJsonCount(1, 'data');

        $exam->forceFill($this->examStateAttributes('INGRESO_ABIERTO'))->save();

        $this->getJson(self::URL)->assertOk()->assertJsonCount(0, 'data');
    }
}
