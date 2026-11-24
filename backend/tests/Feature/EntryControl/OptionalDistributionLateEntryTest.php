<?php

namespace Tests\Feature\EntryControl;

use App\Models\Exam;
use App\Models\StudentExam;
use App\Support\Exams\EntryRoomWarning;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsRoomDistribution;
use Tests\TestCase;

/** HU-031 CA-11 en HU-037: misma regla de avisos que el control ordinario. */
class OptionalDistributionLateEntryTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsRoomDistribution;

    private Exam $exam;
    private int $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedExamCatalog();
        $this->exam = $this->distributionExam(4, [3, 3]);
        $this->saveDistribution($this->exam)->assertOk();
        DB::table('examen')->where('id_examen', $this->exam->id_examen)->update([
            'estado' => Exam::EN_CURSO,
            'ingreso_abierto_en' => now(),
            'ingreso_ordinario_cerrado_en' => now(),
        ]);
        [$this->student] = $this->distributionStudentIds($this->exam);
    }

    public function test_verificar_avisa_y_registrar_exige_confirmar(): void
    {
        $sis = DB::table('estudiante')->where('id_estudiante', $this->student)->value('cod_sis');

        $this->postJson($this->url('verificar'), ['cod_sis' => $sis, 'id_ambiente' => $this->otraAulaId])
            ->assertOk()
            ->assertJsonPath('data.autorizado', true)
            ->assertJsonPath('data.avisos_ambiente.0.tipo', EntryRoomWarning::OUT_OF_ROOM);
        $this->postJson($this->url('registrar'), $this->entry())
            ->assertStatus(409)
            ->assertJsonPath('veredicto', EntryRoomWarning::VERDICT);

        $this->assertDatabaseMissing('examen_estudiante', [
            'id_examen' => $this->exam->id_examen,
            'id_estudiante' => $this->student,
            'estado_ingreso' => StudentExam::CON_RETRASO,
        ]);
    }

    public function test_registrar_confirmado_guarda_ambiente_real_y_marca(): void
    {
        $this->postJson($this->url('registrar'), $this->entry() + ['confirmar_aviso_ambiente' => true])
            ->assertCreated()
            ->assertJsonPath('data.id_ambiente', $this->otraAulaId)
            ->assertJsonPath('data.id_ambiente_planificado', $this->aulaId)
            ->assertJsonPath('data.fuera_de_ambiente', true);

        $this->assertDatabaseHas('examen_estudiante', [
            'id_examen' => $this->exam->id_examen,
            'id_estudiante' => $this->student,
            'estado_ingreso' => StudentExam::CON_RETRASO,
            'id_ambiente' => $this->otraAulaId,
            'id_ambiente_planificado' => $this->aulaId,
        ]);
    }

    public function test_tras_dejar_de_usarla_no_avisa(): void
    {
        $this->postJson("/api/examenes/{$this->exam->id_examen}/distribucion/dejar-de-usar")->assertOk();

        $this->postJson($this->url('registrar'), $this->entry())
            ->assertCreated()
            ->assertJsonPath('data.fuera_de_ambiente', false);
    }

    /** @return array{id_estudiante:int, id_ambiente:int} */
    private function entry(): array
    {
        return ['id_estudiante' => $this->student, 'id_ambiente' => $this->otraAulaId];
    }

    private function url(string $path): string
    {
        return "/api/control-ingreso/examenes/{$this->exam->id_examen}/retrasos/{$path}";
    }
}
