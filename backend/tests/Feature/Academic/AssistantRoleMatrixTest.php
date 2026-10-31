<?php

namespace Tests\Feature\Academic;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsAssistantManagement;
use Tests\Concerns\SeedsSecurityAccounts;
use Tests\TestCase;

/**
 * RNF-02: matriz de roles de los endpoints de auxiliares (HU-08 y HU-09).
 *
 * La gestión es solo del docente activo dueño del grupo o examen. Un Auxiliar, un Administrador,
 * una cuenta sin rol y un docente deshabilitado reciben 403; sin token, 401. La única consulta
 * abierta a cualquier cuenta es la propia del auxiliar (GET /api/auxiliar/examenes), que solo
 * devuelve lo que a esa cuenta le habilitaron.
 */
class AssistantRoleMatrixTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsAssistantManagement;
    use SeedsSecurityAccounts;

    private string $assistantId;

    private string $otherAssistantId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedSecurityAccounts();
        $this->seedAssistantManagement();
        $this->assistantId = $this->createAssistant('Ana', '202400001');
        $this->otherAssistantId = $this->createAssistant('Bruno', '202400002');
    }

    /** @return array<string, array{0: string}> */
    public function nonTeacherActors(): array
    {
        return [
            'auxiliar' => ['auxiliar'],
            'administrador' => ['administrador'],
            'cuenta sin rol' => ['sin rol'],
            'docente deshabilitado' => ['docente inactivo'],
        ];
    }

    /** @dataProvider nonTeacherActors */
    public function test_quien_no_es_docente_activo_recibe_403_al_gestionar_auxiliares(string $actor): void
    {
        $this->putInGroup($this->otherAssistantId, $this->grupoPropioId);
        $this->enableInExam($this->otherAssistantId, $this->examId);
        $before = $this->assistantRows();

        $this->actAsUserId($this->accountFor($actor));

        foreach ($this->roleGatedEndpoints() as $label => [$method, $url, $body]) {
            $this->json($method, $url, $body)->assertForbidden();
        }

        $this->assertSame($before, $this->assistantRows());
    }

    /** @dataProvider nonTeacherActors */
    public function test_quien_no_es_el_docente_dueno_no_ve_los_auxiliares_de_un_examen(string $actor): void
    {
        $this->actAsUserId($this->accountFor($actor));

        $this->getJson("/api/examenes/{$this->examId}/auxiliares")->assertForbidden();
    }

    public function test_sin_token_todos_los_endpoints_de_auxiliares_responden_401(): void
    {
        $this->actAsGuest();

        foreach ($this->roleGatedEndpoints() as [$method, $url, $body]) {
            $this->json($method, $url, $body)->assertUnauthorized();
        }

        $this->getJson("/api/examenes/{$this->examId}/auxiliares")->assertUnauthorized();
        $this->getJson('/api/auxiliar/examenes')->assertUnauthorized();
    }

    public function test_el_docente_dueno_no_recibe_403_en_ningun_endpoint_de_auxiliares(): void
    {
        $this->putInGroup($this->otherAssistantId, $this->grupoPropioId);
        $this->enableInExam($this->otherAssistantId, $this->examId);
        $this->actAsTeacher($this->docenteId);

        foreach ($this->roleGatedEndpoints() as $label => [$method, $url, $body]) {
            $status = $this->json($method, $url, $body)->getStatusCode();

            $this->assertNotContains($status, [401, 403], $label);
            $this->assertLessThan(500, $status, $label);
        }

        $this->getJson("/api/examenes/{$this->examId}/auxiliares")->assertOk();
    }

    public function test_un_docente_ajeno_al_grupo_y_al_examen_recibe_403_en_los_que_dependen_de_la_propiedad(): void
    {
        $this->putInGroup($this->otherAssistantId, $this->grupoPropioId);
        $this->enableInExam($this->otherAssistantId, $this->examId);
        $this->actAsTeacher($this->otroDocenteId);

        $this->getJson("/api/examenes/{$this->examId}/auxiliares")->assertForbidden();
        $this->putJson(
            "/api/examenes/{$this->examId}/auxiliares/{$this->otherAssistantId}/ambiente",
            ['id_ambiente' => 1]
        )->assertForbidden();
        $this->postJson("/api/docente/grupos/{$this->grupoPropioId}/auxiliares", [
            'id_usuario' => $this->assistantId,
        ])->assertForbidden();
    }

    public function test_cada_cuenta_solo_ve_en_su_consulta_los_examenes_donde_la_habilitaron(): void
    {
        $this->enableInExam($this->assistantId, $this->examId);

        $this->actAsUserId($this->assistantId);
        $this->getJson('/api/auxiliar/examenes')->assertOk()->assertJsonCount(1, 'data');

        foreach (['administrador', 'sin rol', 'auxiliar sin habilitar'] as $actor) {
            $this->actAsUserId($this->accountFor($actor));

            $this->getJson('/api/auxiliar/examenes')->assertOk()->assertJsonCount(0, 'data');
        }

        $this->actAsTeacher($this->docenteId);
        $this->getJson('/api/auxiliar/examenes')->assertOk()->assertJsonCount(0, 'data');
    }

    /** @return array<string, array{0: string, 1: string, 2: array}> etiqueta => método, URL y cuerpo */
    private function roleGatedEndpoints(): array
    {
        $group = $this->grupoPropioId;
        $exam = $this->examId;
        $user = $this->otherAssistantId;

        return [
            'listar auxiliares' => ['GET', '/api/docente/auxiliares', []],
            'buscar auxiliares' => ['GET', '/api/docente/auxiliares/buscar?criterio=Bruno', []],
            'listar grupos' => ['GET', '/api/docente/grupos', []],
            'añadir a un grupo' => ['POST', "/api/docente/grupos/{$group}/auxiliares", ['id_usuario' => $user]],
            'añadir a varios grupos' => ['POST', "/api/docente/auxiliares/{$user}/grupos", ['grupos' => [$group]]],
            'habilitar para un examen' => ['POST', "/api/docente/examenes/{$exam}/auxiliares", ['id_usuario' => $user]],
            'quitar de un grupo' => ['DELETE', "/api/docente/grupos/{$group}/auxiliares/{$user}", []],
            'quitar de un examen' => ['DELETE', "/api/docente/examenes/{$exam}/auxiliares/{$user}", []],
            'asignar ambiente' => ['PUT', "/api/examenes/{$exam}/auxiliares/{$user}/ambiente", ['id_ambiente' => 1]],
        ];
    }

    private function accountFor(string $actor): string
    {
        switch ($actor) {
            case 'auxiliar':
                return $this->assistantId;
            case 'administrador':
                return $this->createAccount(Role::ADMINISTRADOR)->id_usuario;
            case 'docente inactivo':
                return $this->createAccount(Role::DOCENTE, User::ESTADO_INACTIVO)->id_usuario;
            case 'auxiliar sin habilitar':
                return $this->createAccount(Role::AUXILIAR)->id_usuario;
            default:
                return $this->createAccount()->id_usuario;
        }
    }

    /** @return array<string, int> filas de las tablas de auxiliares, para comprobar que no cambiaron */
    private function assistantRows(): array
    {
        return [
            'grupo_auxiliar' => DB::table('grupo_auxiliar')->count(),
            'grupo_auxiliar_activos' => DB::table('grupo_auxiliar')->where('estado', 'ACTIVO')->count(),
            'examen_auxiliar' => DB::table('examen_auxiliar')->count(),
            'examen_auxiliar_con_ambiente' => DB::table('examen_auxiliar')->whereNotNull('id_ambiente')->count(),
        ];
    }
}
