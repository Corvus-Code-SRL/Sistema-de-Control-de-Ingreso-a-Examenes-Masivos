<?php

namespace Tests\Feature\Security;

use App\Models\Exam;
use App\Models\Role;
use App\Services\Security\UserRoleService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\AssignsIncidentRole;
use Tests\Concerns\SeedsExamCatalog;
use Tests\TestCase;

/**
 * El rol Invitado (docente sin código SIS) ejerce la docencia pero no la Central de Riesgo, los
 * auxiliares de grupo, ni las funciones aún sin construir (config sciem.access).
 */
class InvitedTeacherAccessTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsExamCatalog;
    use AssignsIncidentRole;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedExamCatalog();
        $this->makeGuest($this->docenteId);
        $this->actAsTeacher($this->docenteId);
    }

    private function makeGuest(string $userId): void
    {
        DB::table('usuario')->where('id_usuario', $userId)->update(['cod_sis' => 'INV4821']);
        DB::table('usuario_rol')->where('id_usuario', $userId)->whereNull('fecha_fin')
            ->update(['fecha_fin' => now()]);
        $this->assignRole($userId, Role::INVITADO);
    }

    public function test_el_mapa_de_acceso_define_las_ocho_funciones_con_la_decision_del_invitado(): void
    {
        $access = config('sciem.access');

        $this->assertSame([
            'teaching', 'exam_assistants', 'incidents', 'risk_center',
            'group_assistants', 'report_assistant', 'special_cases', 'invite_teachers',
        ], array_keys($access));

        $allowed = [
            'teaching' => true, 'exam_assistants' => true, 'incidents' => true, 'risk_center' => false,
            'group_assistants' => false, 'report_assistant' => false, 'special_cases' => false,
            'invite_teachers' => false,
        ];

        foreach ($allowed as $feature => $invitedCan) {
            $this->assertSame($invitedCan, in_array(Role::INVITADO, $access[$feature], true), $feature);
            $this->assertContains(Role::DOCENTE, $access[$feature], "El Docente conserva {$feature}");
        }
    }

    public function test_invitado_registra_un_examen_propio(): void
    {
        $this->postJson('/api/examenes', $this->validPayload())
            ->assertCreated()
            ->assertJsonPath('data.id_usuario_docente', $this->docenteId);
    }

    public function test_invitado_inicia_su_examen(): void
    {
        $exam = $this->createExam(['estado' => Exam::EN_INGRESO]);

        $this->postJson("/api/examenes/{$exam->id_examen}/iniciar")
            ->assertOk()
            ->assertJsonPath('data.estado', Exam::EN_CURSO);
    }

    public function test_invitado_ve_sus_grupos_y_la_nomina_de_un_grupo_propio(): void
    {
        $this->getJson("/api/grupos/{$this->grupoPropioId}/estudiantes")->assertOk();
        $this->getJson("/api/grupos/{$this->grupoPropioId}")->assertOk();
    }

    public function test_invitado_habilita_un_auxiliar_para_su_examen_y_no_tiene_sugeridos(): void
    {
        $exam = $this->createExam();
        $assistant = $this->createAssistantAccount('202400001');

        $this->getJson("/api/docente/examenes/{$exam->id_examen}/auxiliares/sugeridos")
            ->assertOk()
            ->assertExactJson(['data' => []]);

        $this->getJson('/api/docente/auxiliares/buscar?criterio=Auxiliar')->assertOk();

        $this->postJson("/api/docente/examenes/{$exam->id_examen}/auxiliares", ['id_usuario' => $assistant])
            ->assertSuccessful();

        $this->assertTrue(
            DB::table('examen_auxiliar')
                ->where('id_examen', $exam->id_examen)
                ->where('id_usuario', $assistant)
                ->exists()
        );
    }

    public function test_invitado_no_consulta_la_central_de_riesgo(): void
    {
        $this->getJson('/api/central-riesgo')->assertForbidden();
        $this->getJson('/api/central-riesgo/1')->assertForbidden();
        $this->getJson('/api/central-riesgo/1/evidencia')->assertForbidden();
    }

    public function test_invitado_no_gestiona_auxiliares_de_grupo(): void
    {
        $assistant = $this->createAssistantAccount('202400002');
        $group = $this->grupoPropioId;

        $this->getJson('/api/docente/auxiliares')->assertForbidden();
        $this->getJson('/api/docente/grupos')->assertForbidden();
        $this->getJson("/api/grupos/{$group}/auxiliares")->assertForbidden();
        $this->postJson("/api/docente/grupos/{$group}/auxiliares", ['id_usuario' => $assistant])->assertForbidden();
        $this->postJson("/api/docente/auxiliares/{$assistant}/grupos", ['grupos' => [$group]])->assertForbidden();
        $this->deleteJson("/api/docente/grupos/{$group}/auxiliares/{$assistant}")->assertForbidden();
    }

    public function test_un_docente_sigue_gestionando_auxiliares_de_grupo_y_la_central_de_riesgo(): void
    {
        $this->actAsTeacher($this->otroDocenteId);

        $this->getJson('/api/docente/auxiliares')->assertOk();
        $this->getJson('/api/docente/grupos')->assertOk();
        $this->getJson('/api/central-riesgo')->assertOk();
    }

    public function test_las_funciones_aun_sin_construir_solo_las_ejerce_el_docente(): void
    {
        $roles = app(UserRoleService::class);

        foreach (['report_assistant', 'special_cases', 'invite_teachers'] as $feature) {
            $this->assertFalse($roles->canUse($this->docenteId, $feature), "Invitado: {$feature}");
            $this->assertTrue($roles->canUse($this->otroDocenteId, $feature), "Docente: {$feature}");
        }
    }

    public function test_una_funcion_sin_definir_en_el_mapa_es_un_error_de_programacion(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(UserRoleService::class)->canUse($this->docenteId, 'inexistente');
    }

    private function createAssistantAccount(string $sis): string
    {
        $id = (string) \Illuminate\Support\Str::uuid();

        DB::table('usuario')->insert([
            'id_usuario' => $id,
            'nombre' => 'Aux',
            'apellido_paterno' => 'Auxiliar',
            'correo' => "aux.{$sis}@test.com",
            'contrasenia' => 'x',
            'cod_sis' => $sis,
            'estado' => 'ACTIVO',
        ]);
        $this->assignAuxiliarRole($id);

        return $id;
    }
}
