<?php

namespace Tests\Feature\Academic;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsAssistantManagement;
use Tests\TestCase;

/**
 * HU-08: quién puede gestionar auxiliares. El Auxiliar nunca; un docente solo en sus
 * grupos y exámenes.
 */
class AssistantAuthorizationTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsAssistantManagement;

    private string $auxiliarId;

    private string $otroAuxiliarId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAssistantManagement();
        $this->auxiliarId = $this->createAssistant('Ana', '202400001');
        $this->otroAuxiliarId = $this->createAssistant('Bruno', '202400002');
    }

    /** @return array<string, array{0: string, 1: string}> método HTTP y URL de cada endpoint de HU-08 */
    private function endpoints(): array
    {
        $group = $this->grupoPropioId;
        $exam = $this->examId;
        $user = $this->otroAuxiliarId;

        return [
            'listar auxiliares' => ['GET', '/api/docente/auxiliares'],
            'buscar auxiliares' => ['GET', '/api/docente/auxiliares/buscar?criterio=Bruno'],
            'listar grupos' => ['GET', '/api/docente/grupos'],
            'añadir a un grupo' => ['POST', "/api/docente/grupos/{$group}/auxiliares"],
            'añadir a varios grupos' => ['POST', "/api/docente/auxiliares/{$user}/grupos"],
            'habilitar para un examen' => ['POST', "/api/docente/examenes/{$exam}/auxiliares"],
            'quitar de un grupo' => ['DELETE', "/api/docente/grupos/{$group}/auxiliares/{$user}"],
            'quitar de un examen' => ['DELETE', "/api/docente/examenes/{$exam}/auxiliares/{$user}"],
        ];
    }

    private function bodyFor(string $method, string $url): array
    {
        if ($method !== 'POST') {
            return [];
        }

        return str_contains($url, '/grupos') && str_contains($url, '/auxiliares/')
            ? ['grupos' => [$this->grupoPropioId]]
            : ['id_usuario' => $this->otroAuxiliarId];
    }

    public function test_un_auxiliar_recibe_403_en_todos_los_endpoints_de_gestion(): void
    {
        $this->putInGroup($this->otroAuxiliarId, $this->grupoPropioId);
        $this->enableInExam($this->otroAuxiliarId, $this->examId);
        $this->actAsUserId($this->auxiliarId);

        foreach ($this->endpoints() as $label => [$method, $url]) {
            $this->json($method, $url, $this->bodyFor($method, $url))
                ->assertForbidden()
                ->assertJsonPath('message', 'Solo un docente puede gestionar auxiliares.');
        }

        // Ninguna de las llamadas cambió datos.
        $this->assertSame('ACTIVO', $this->groupStatus($this->otroAuxiliarId, $this->grupoPropioId));
        $this->assertTrue($this->isEnabledInExam($this->otroAuxiliarId, $this->examId));
        $this->assertSame(1, DB::table('grupo_auxiliar')->count());
    }

    public function test_un_docente_responde_normal_donde_el_auxiliar_recibe_403(): void
    {
        foreach (['listar auxiliares', 'buscar auxiliares', 'listar grupos'] as $label) {
            [$method, $url] = $this->endpoints()[$label];

            $this->json($method, $url)->assertOk();
        }
    }

    public function test_un_docente_ajeno_recibe_403_al_gestionar_el_grupo_o_el_examen_de_otro(): void
    {
        $this->putInGroup($this->otroAuxiliarId, $this->grupoPropioId);
        $this->enableInExam($this->otroAuxiliarId, $this->examId);
        $this->actAsTeacher($this->otroDocenteId);

        $this->postJson("/api/docente/grupos/{$this->grupoPropioId}/auxiliares", [
            'id_usuario' => $this->auxiliarId,
        ])->assertForbidden();

        $this->postJson("/api/docente/auxiliares/{$this->auxiliarId}/grupos", [
            'grupos' => [$this->grupoPropioId],
        ])->assertForbidden();

        $this->postJson("/api/docente/examenes/{$this->examId}/auxiliares", [
            'id_usuario' => $this->otroAuxiliarId,
        ])->assertForbidden();

        $this->deleteJson("/api/docente/grupos/{$this->grupoPropioId}/auxiliares/{$this->otroAuxiliarId}")
            ->assertForbidden();

        $this->deleteJson("/api/docente/examenes/{$this->examId}/auxiliares/{$this->otroAuxiliarId}")
            ->assertForbidden();

        $this->assertNull($this->groupStatus($this->auxiliarId, $this->grupoPropioId));
        $this->assertSame('ACTIVO', $this->groupStatus($this->otroAuxiliarId, $this->grupoPropioId));
        $this->assertTrue($this->isEnabledInExam($this->otroAuxiliarId, $this->examId));
    }

    public function test_una_lista_con_un_grupo_ajeno_y_otro_propio_se_rechaza_completa_con_403(): void
    {
        $this->postJson("/api/docente/auxiliares/{$this->auxiliarId}/grupos", [
            'grupos' => [$this->grupoPropioId, $this->grupoAjenoId],
        ])->assertForbidden();

        $this->assertSame(0, DB::table('grupo_auxiliar')->where('id_usuario', $this->auxiliarId)->count());
    }

    public function test_un_grupo_inexistente_en_la_lista_responde_422(): void
    {
        $this->postJson("/api/docente/auxiliares/{$this->auxiliarId}/grupos", [
            'grupos' => [$this->grupoPropioId, 2147483000],
        ])->assertStatus(422)->assertJsonValidationErrors('grupos');
    }

    public function test_los_identificadores_con_formato_invalido_responden_404_sin_llegar_a_la_base(): void
    {
        $this->deleteJson("/api/docente/grupos/{$this->grupoPropioId}/auxiliares/no-es-uuid")->assertNotFound();
        $this->deleteJson("/api/docente/examenes/{$this->examId}/auxiliares/no-es-uuid")->assertNotFound();
        $this->deleteJson("/api/docente/grupos/abc/auxiliares/{$this->auxiliarId}")->assertNotFound();
        $this->postJson('/api/docente/grupos/abc/auxiliares', ['id_usuario' => $this->auxiliarId])->assertNotFound();
        $this->postJson('/api/docente/examenes/abc/auxiliares', ['id_usuario' => $this->auxiliarId])->assertNotFound();
        $this->postJson('/api/docente/auxiliares/no-es-uuid/grupos', ['grupos' => [1]])->assertNotFound();
    }

    public function test_valida_el_maximo_y_los_repetidos_en_la_lista_de_grupos(): void
    {
        $this->postJson("/api/docente/auxiliares/{$this->auxiliarId}/grupos", [
            'grupos' => [$this->grupoPropioId, 99999999999],
        ])->assertStatus(422)->assertJsonValidationErrors('grupos.1');

        $this->postJson("/api/docente/auxiliares/{$this->auxiliarId}/grupos", [
            'grupos' => [$this->grupoPropioId, $this->grupoPropioId],
        ])->assertStatus(422)->assertJsonValidationErrors(['grupos.0', 'grupos.1']);

        $this->assertSame(0, DB::table('grupo_auxiliar')->count());
    }
}
