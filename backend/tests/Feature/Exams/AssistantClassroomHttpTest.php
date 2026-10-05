<?php

namespace Tests\Feature\Exams;

use App\Models\Exam;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsExamAssistants;
use Tests\TestCase;

/**
 * Endpoints de HU-09: el docente asigna el ambiente de cada auxiliar y el auxiliar
 * consulta el suyo.
 */
class AssistantClassroomHttpTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsExamAssistants;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedExamAssistants();
    }

    public function test_prueba_de_aceptacion_asigna_tres_auxiliares_y_reasigna_uno(): void
    {
        $exam = $this->examWithAssistants();

        $this->assign($exam, $this->mariaId, $this->aulaId)->assertOk();
        $this->assign($exam, $this->jorgeId, $this->otraAulaId)->assertOk();
        $this->assign($exam, $this->danielaId, $this->aulaId)->assertOk();

        $this->assign($exam, $this->danielaId, $this->otraAulaId)
            ->assertOk()
            ->assertJsonPath('mensaje', 'Ambiente asignado correctamente.')
            ->assertJsonPath('data.id_usuario', $this->danielaId)
            ->assertJsonPath('data.nombre_completo', 'Daniela Ferrufino Soliz')
            ->assertJsonPath('data.ambiente.nro_aula', '692B');

        $this->assertAssignedTo($exam, $this->mariaId, $this->aulaId);
        $this->assertAssignedTo($exam, $this->jorgeId, $this->otraAulaId);
        $this->assertAssignedTo($exam, $this->danielaId, $this->otraAulaId);
    }

    public function test_lista_los_auxiliares_del_examen_con_su_ambiente(): void
    {
        $exam = $this->examWithAssistants();
        $this->assign($exam, $this->jorgeId, $this->otraAulaId);

        $this->getJson("/api/examenes/{$exam->id_examen}/auxiliares")
            ->assertOk()
            ->assertJsonPath('data.estado', Exam::PROGRAMADO)
            ->assertJsonPath('data.editable', true)
            ->assertJsonCount(2, 'data.ambientes')
            ->assertJsonCount(3, 'data.auxiliares')
            ->assertJsonPath('data.auxiliares.0.nombre_completo', 'Daniela Ferrufino Soliz')
            ->assertJsonPath('data.auxiliares.0.ambiente', null)
            ->assertJsonPath('data.auxiliares.1.cod_sis', '202000871')
            ->assertJsonPath('data.auxiliares.1.ambiente.nro_aula', '692B');
    }

    public function test_desde_en_ingreso_responde_409_y_el_listado_queda_de_solo_lectura(): void
    {
        $exam = $this->examWithAssistants(['estado' => Exam::EN_INGRESO], $this->aulaId);

        $this->assign($exam, $this->mariaId, $this->otraAulaId)
            ->assertStatus(409)
            ->assertJsonPath(
                'message',
                'El control de ingreso del examen ya se inició: los ambientes de los auxiliares quedaron fijos.'
            );

        $this->getJson("/api/examenes/{$exam->id_examen}/auxiliares")
            ->assertOk()
            ->assertJsonPath('data.editable', false);

        $this->assertAssignedTo($exam, $this->mariaId, $this->aulaId);
    }

    public function test_valida_el_ambiente_enviado(): void
    {
        $exam = $this->examWithAssistants();

        $this->putJson("/api/examenes/{$exam->id_examen}/auxiliares/{$this->mariaId}/ambiente", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['id_ambiente' => 'Debe seleccionar un ambiente.']);

        $this->assign($exam, $this->mariaId, $this->aulaInactivaId)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['id_ambiente' => 'El ambiente seleccionado no pertenece a este examen.']);
    }

    public function test_responde_404_si_el_auxiliar_no_esta_habilitado_o_el_id_no_es_uuid(): void
    {
        $exam = $this->createExam([], [$this->aulaId]);

        $this->assign($exam, $this->mariaId, $this->aulaId)
            ->assertNotFound()
            ->assertJsonPath('message', 'El auxiliar no está habilitado para este examen.');

        $this->putJson("/api/examenes/{$exam->id_examen}/auxiliares/no-es-uuid/ambiente", [
            'id_ambiente' => $this->aulaId,
        ])->assertNotFound();
    }

    public function test_responde_404_si_el_examen_no_existe(): void
    {
        $this->getJson('/api/examenes/999999/auxiliares')->assertNotFound();
    }

    public function test_responde_403_con_el_examen_de_otro_docente(): void
    {
        $exam = $this->examWithAssistants(['id_usuario_docente' => $this->otroDocenteId]);

        $this->getJson("/api/examenes/{$exam->id_examen}/auxiliares")->assertForbidden();
        $this->assign($exam, $this->mariaId, $this->aulaId)->assertForbidden();

        $this->assertAssignedTo($exam, $this->mariaId, null);
    }

    public function test_el_auxiliar_consulta_sus_examenes_con_el_ambiente_asignado(): void
    {
        $exam = $this->examWithAssistants(['nombre_examen' => 'Primer parcial', 'hora_inicio' => '08:00']);
        $this->assign($exam, $this->mariaId, $this->otraAulaId);
        $this->actingAs(User::findOrFail($this->mariaId));

        $this->getJson('/api/auxiliar/examenes')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_examen', $exam->id_examen)
            ->assertJsonPath('data.0.nombre_examen', 'Primer parcial')
            ->assertJsonPath('data.0.hora_inicio', '08:00')
            ->assertJsonPath('data.0.estado', Exam::PROGRAMADO)
            ->assertJsonPath('data.0.ambiente.nro_aula', '692B');
    }

    public function test_el_auxiliar_no_puede_asignar_ambientes_y_recibe_403(): void
    {
        $exam = $this->examWithAssistants();
        $this->giveRoleTo($this->mariaId, Role::AUXILIAR);
        $this->actAsUserId($this->mariaId);

        $this->assign($exam, $this->jorgeId, $this->aulaId)
            ->assertForbidden()
            ->assertJsonPath('message', 'Solo un docente puede asignar ambientes a los auxiliares.');

        $this->assertAssignedTo($exam, $this->jorgeId, null);
    }

    public function test_un_docente_ajeno_al_examen_recibe_403_al_asignar(): void
    {
        $exam = $this->examWithAssistants();
        $this->actAsTeacher($this->otroDocenteId);

        $this->assign($exam, $this->mariaId, $this->aulaId)->assertForbidden();

        $this->assertAssignedTo($exam, $this->mariaId, null);
    }

    public function test_la_consulta_del_auxiliar_sin_sesion_responde_401(): void
    {
        $this->actAsGuest();

        $this->getJson('/api/auxiliar/examenes')->assertUnauthorized();
    }

    public function test_el_auxiliar_solo_ve_los_examenes_donde_esta_habilitado(): void
    {
        $this->examWithAssistants();
        $this->actingAs(User::findOrFail($this->mariaId));
        DB::table('examen_auxiliar')->where('id_usuario', $this->mariaId)->delete();

        $this->getJson('/api/auxiliar/examenes')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    private function giveRoleTo(string $userId, string $roleName): void
    {
        $this->seed(RoleSeeder::class);

        DB::table('usuario_rol')->insert([
            'id_usuario'   => $userId,
            'id_rol'       => Role::where('nombre_rol', $roleName)->value('id_rol'),
            'fecha_inicio' => '2026-01-10 08:00:00',
        ]);
    }

    private function assign(Exam $exam, string $userId, int $classroomId): \Illuminate\Testing\TestResponse
    {
        return $this->putJson("/api/examenes/{$exam->id_examen}/auxiliares/{$userId}/ambiente", [
            'id_ambiente' => $classroomId,
        ]);
    }
}
