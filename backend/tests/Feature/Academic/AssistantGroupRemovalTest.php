<?php

namespace Tests\Feature\Academic;

use App\Models\AuditLog;
use App\Models\Exam;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsAssistantManagement;
use Tests\TestCase;

/**
 * HU-08: al quitar a un auxiliar de un grupo se le deshabilita de los exámenes del docente
 * en los que ese grupo era su último vínculo.
 */
class AssistantGroupRemovalTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsAssistantManagement;

    private string $auxiliarId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAssistantManagement();
        $this->auxiliarId = $this->createAssistant('Ana', '202400001');
    }

    private function removeFromGroup(int $groupId)
    {
        return $this->deleteJson("/api/docente/grupos/{$groupId}/auxiliares/{$this->auxiliarId}");
    }

    private function logCount(string $operation): int
    {
        return AuditLog::where(
            'id_accion',
            DB::table('accion')->where('operacion', $operation)->value('id_accion')
        )->count();
    }

    public function test_si_queda_otro_grupo_vinculado_al_examen_sigue_habilitado(): void
    {
        DB::table('grupo_examen')->insert(['id_grupo' => $this->segundoGrupoPropioId, 'id_examen' => $this->examId]);
        $this->putInGroup($this->auxiliarId, $this->grupoPropioId);
        $this->putInGroup($this->auxiliarId, $this->segundoGrupoPropioId);
        $this->enableInExam($this->auxiliarId, $this->examId);

        $this->removeFromGroup($this->grupoPropioId)->assertOk();

        $this->assertSame('INACTIVO', $this->groupStatus($this->auxiliarId, $this->grupoPropioId));
        $this->assertTrue($this->isEnabledInExam($this->auxiliarId, $this->examId));
        $this->assertSame(0, $this->logCount('QUITAR_AUXILIAR_EXAMEN'));
    }

    public function test_si_no_queda_ningun_grupo_vinculado_se_le_deshabilita_del_examen_y_se_registra(): void
    {
        $this->putInGroup($this->auxiliarId, $this->grupoPropioId);
        // El segundo grupo no está vinculado al examen: no cuenta como vínculo.
        $this->putInGroup($this->auxiliarId, $this->segundoGrupoPropioId);
        $this->enableInExam($this->auxiliarId, $this->examId);

        $this->removeFromGroup($this->grupoPropioId)->assertOk();

        $this->assertSame('INACTIVO', $this->groupStatus($this->auxiliarId, $this->grupoPropioId));
        $this->assertSame('ACTIVO', $this->groupStatus($this->auxiliarId, $this->segundoGrupoPropioId));
        $this->assertFalse($this->isEnabledInExam($this->auxiliarId, $this->examId));
        $this->assertSame(1, $this->logCount('QUITAR_AUXILIAR_GRUPO'));
        $this->assertSame(1, $this->logCount('QUITAR_AUXILIAR_EXAMEN'));
    }

    public function test_el_otro_vinculo_inactivo_no_cuenta(): void
    {
        DB::table('grupo_examen')->insert(['id_grupo' => $this->segundoGrupoPropioId, 'id_examen' => $this->examId]);
        $this->putInGroup($this->auxiliarId, $this->grupoPropioId);
        $this->putInGroup($this->auxiliarId, $this->segundoGrupoPropioId);
        DB::table('grupo_auxiliar')
            ->where('id_grupo', $this->segundoGrupoPropioId)
            ->where('id_usuario', $this->auxiliarId)
            ->update(['estado' => 'INACTIVO']);
        $this->enableInExam($this->auxiliarId, $this->examId);

        $this->removeFromGroup($this->grupoPropioId)->assertOk();

        $this->assertFalse($this->isEnabledInExam($this->auxiliarId, $this->examId));
    }

    /** @dataProvider startedStates */
    public function test_un_examen_en_ingreso_o_en_curso_bloquea_la_baja_con_422(string $state): void
    {
        $this->putInGroup($this->auxiliarId, $this->grupoPropioId);
        $this->enableInExam($this->auxiliarId, $this->examId);
        DB::table('examen')->where('id_examen', $this->examId)->update(['estado' => $state]);

        $this->removeFromGroup($this->grupoPropioId)
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_grupo');

        $this->assertSame('ACTIVO', $this->groupStatus($this->auxiliarId, $this->grupoPropioId));
        $this->assertTrue($this->isEnabledInExam($this->auxiliarId, $this->examId));
        $this->assertSame(0, $this->logCount('QUITAR_AUXILIAR_GRUPO'));
    }

    /** @return array<string, array{0: string}> */
    public function startedStates(): array
    {
        return [
            'EN_INGRESO' => [Exam::EN_INGRESO],
            'EN_CURSO' => [Exam::EN_CURSO],
        ];
    }

    public function test_un_examen_en_ingreso_con_otro_grupo_vinculado_no_bloquea_la_baja(): void
    {
        DB::table('grupo_examen')->insert(['id_grupo' => $this->segundoGrupoPropioId, 'id_examen' => $this->examId]);
        $this->putInGroup($this->auxiliarId, $this->grupoPropioId);
        $this->putInGroup($this->auxiliarId, $this->segundoGrupoPropioId);
        $this->enableInExam($this->auxiliarId, $this->examId);
        DB::table('examen')->where('id_examen', $this->examId)->update(['estado' => Exam::EN_INGRESO]);

        $this->removeFromGroup($this->grupoPropioId)->assertOk();

        $this->assertTrue($this->isEnabledInExam($this->auxiliarId, $this->examId));
    }

    public function test_la_baja_no_toca_examenes_finalizados_ni_los_de_otro_docente(): void
    {
        $this->putInGroup($this->auxiliarId, $this->grupoPropioId);
        $this->putInGroup($this->auxiliarId, $this->grupoAjenoId);

        $finished = $this->createExamLinkedTo([$this->grupoPropioId], Exam::FINALIZADO, null, 'Finalizado');
        $foreign = $this->createExamLinkedTo([$this->grupoAjenoId], Exam::PROGRAMADO, $this->otroDocenteId, 'Ajeno');
        $this->enableInExam($this->auxiliarId, $finished);
        $this->enableInExam($this->auxiliarId, $foreign, $this->otroDocenteId);

        $this->removeFromGroup($this->grupoPropioId)->assertOk();

        $this->assertTrue($this->isEnabledInExam($this->auxiliarId, $finished));
        $this->assertTrue($this->isEnabledInExam($this->auxiliarId, $foreign));
        $this->assertSame('ACTIVO', $this->groupStatus($this->auxiliarId, $this->grupoAjenoId));
    }
}
