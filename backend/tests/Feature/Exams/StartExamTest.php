<?php

namespace Tests\Feature\Exams;

use App\Models\Action;
use App\Models\AuditLog;
use App\Models\Exam;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\SeedsExamCatalog;
use Tests\TestCase;

class StartExamTest extends TestCase
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
        return "/api/examenes/{$exam->id_examen}/iniciar";
    }

    public function test_docente_inicia_examen_en_ingreso_y_se_audita(): void
    {
        $exam = $this->createExam(['estado' => Exam::EN_INGRESO]);

        $this->postJson($this->url($exam))
            ->assertOk()
            ->assertJsonPath('mensaje', 'Examen iniciado correctamente.')
            ->assertJsonPath('data.estado', Exam::EN_CURSO)
            ->assertJsonPath('data.inicio_automatico', false);

        $exam->refresh();
        $this->assertNotNull($exam->inicio_real_en);
        $this->assertSame(Exam::EN_CURSO, $exam->estado);

        $log = AuditLog::query()->where('tabla_afectada', 'examen')->sole();
        $this->assertSame('INICIAR', Action::query()->whereKey($log->id_accion)->value('operacion'));
        $this->assertSame($this->docenteId, $log->id_usuario);
    }

    public function test_no_se_puede_iniciar_dos_veces(): void
    {
        $exam = $this->createExam(['estado' => Exam::EN_INGRESO]);

        $this->postJson($this->url($exam))->assertOk();
        $this->postJson($this->url($exam))->assertStatus(409);
        $this->assertSame(1, AuditLog::query()->where('tabla_afectada', 'examen')->count());
    }

    public function test_docente_ajeno_no_puede_iniciar(): void
    {
        $exam = $this->createExam([
            'estado' => Exam::EN_INGRESO,
            'id_usuario_docente' => $this->otroDocenteId,
        ]);

        $this->postJson($this->url($exam))->assertForbidden();
        $this->assertSame(Exam::EN_INGRESO, $exam->fresh()->estado);
    }

    public function test_solo_se_inicia_desde_en_ingreso(): void
    {
        foreach ([Exam::PROGRAMADO, Exam::EN_CURSO, Exam::FINALIZADO, Exam::CANCELADO] as $state) {
            $exam = $this->createExam(['estado' => $state]);
            $this->postJson($this->url($exam))->assertStatus(409);
            $this->assertSame($state, $exam->fresh()->estado);
        }
    }
}
