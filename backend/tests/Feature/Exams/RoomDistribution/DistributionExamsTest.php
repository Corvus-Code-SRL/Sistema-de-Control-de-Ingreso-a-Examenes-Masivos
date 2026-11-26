<?php

namespace Tests\Feature\Exams\RoomDistribution;

use App\Models\Exam;
use App\Models\ExamInvitation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\SeedsRoomDistribution;
use Tests\TestCase;

class DistributionExamsTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsRoomDistribution;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedExamCatalog();
    }

    public function test_selector_directo_incluye_programados_propios_y_colaborados_con_su_edicion(): void
    {
        $own = $this->distributionExam();
        $collaboration = $this->createExam(['id_usuario_docente' => $this->otroDocenteId], [
            $this->aulaId, $this->otraAulaId,
        ]);
        $this->createInvitation($collaboration, $this->docenteId, ExamInvitation::ACEPTADA);
        $this->createExam(['id_usuario_docente' => $this->otroDocenteId]);
        $this->createExam(['estado' => Exam::EN_CURSO]);
        $this->createExam(['estado' => Exam::FINALIZADO]);
        $this->createExam(['estado' => Exam::CANCELADO]);

        $response = $this->getJson('/api/examenes/distribucion')->assertOk()->assertJsonCount(2, 'data');
        $options = array_column($response->json('data'), null, 'id_examen');
        $this->assertTrue($options[$own->id_examen]['es_creador']);
        $this->assertTrue($options[$own->id_examen]['editable']);
        $this->assertFalse($options[$collaboration->id_examen]['es_creador']);
        $this->assertFalse($options[$collaboration->id_examen]['editable']);
        $this->assertSame(2, $options[$own->id_examen]['cantidad_ambientes']);
        $this->assertSame(['nombre' => 'Calculo II'], $options[$own->id_examen]['materia']);
    }

    public function test_abierto_sigue_en_programados_pero_se_muestra_solo_lectura(): void
    {
        $exam = $this->distributionExam();
        $this->putExamInState((int) $exam->id_examen, 'INGRESO_ABIERTO');

        $this->getJson('/api/examenes/distribucion')->assertOk()
            ->assertJsonPath('data.0.id_examen', $exam->id_examen)->assertJsonPath('data.0.editable', false);
    }

    public function test_no_incluye_invitaciones_pendientes_rechazadas_o_sin_efecto(): void
    {
        foreach ([ExamInvitation::PENDIENTE, ExamInvitation::RECHAZADA, ExamInvitation::SIN_EFECTO] as $state) {
            $exam = $this->createExam(['id_usuario_docente' => $this->otroDocenteId]);
            $this->createInvitation($exam, $this->docenteId, $state);
        }

        $this->getJson('/api/examenes/distribucion')->assertOk()->assertExactJson(['data' => []]);
    }
}
