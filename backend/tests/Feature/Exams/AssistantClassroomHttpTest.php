<?php

namespace Tests\Feature\Exams;

use App\Models\Exam;
use App\Models\ExamAssistant;
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

    public function test_el_auxiliar_ve_el_unico_ambiente_del_examen_cuando_no_tiene_asignacion(): void
    {
        $exam = $this->createExam(['nombre_examen' => 'Un solo ambiente'], [$this->aulaId]);
        $this->enableAssistant($exam, $this->mariaId);
        $this->actingAs(User::findOrFail($this->mariaId));

        $this->getJson('/api/auxiliar/examenes')
            ->assertOk()
            ->assertJsonPath('data.0.id_examen', $exam->id_examen)
            ->assertJsonPath('data.0.ambiente.nro_aula', '691A')
            ->assertJsonPath('data.0.ambiente_por_defecto', true);

        // La regla se resuelve al consultar: no se escribe nada en examen_auxiliar.
        $this->assertAssignedTo($exam, $this->mariaId, null);
    }

    public function test_con_varios_ambientes_y_sin_asignacion_el_auxiliar_no_tiene_ambiente(): void
    {
        $this->examWithAssistants();
        $this->actingAs(User::findOrFail($this->mariaId));

        $this->getJson('/api/auxiliar/examenes')
            ->assertOk()
            ->assertJsonPath('data.0.ambiente', null)
            ->assertJsonPath('data.0.ambiente_por_defecto', false);
    }

    public function test_la_asignacion_explicita_gana_sobre_el_ambiente_por_defecto(): void
    {
        $exam = $this->createExam([], [$this->aulaId]);
        $this->enableAssistant($exam, $this->mariaId, $this->aulaId);
        $this->actingAs(User::findOrFail($this->mariaId));

        $this->getJson('/api/auxiliar/examenes')
            ->assertOk()
            ->assertJsonPath('data.0.ambiente.nro_aula', '691A')
            ->assertJsonPath('data.0.ambiente_por_defecto', false);
    }

    public function test_el_ambiente_por_defecto_se_ve_tambien_con_el_ingreso_abierto(): void
    {
        $exam = $this->createExam(['estado' => Exam::EN_INGRESO], [$this->aulaId]);
        $this->enableAssistant($exam, $this->mariaId);
        $this->actingAs(User::findOrFail($this->mariaId));

        $this->getJson('/api/auxiliar/examenes')
            ->assertOk()
            ->assertJsonPath('data.0.estado', Exam::EN_INGRESO)
            ->assertJsonPath('data.0.ambiente.nro_aula', '691A');
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

    public function test_no_permite_asignar_auxiliar_a_dos_aulas_en_horarios_superpuestos(): void
    {
        // Primer examen: 08:00 a 09:30, aula 691A.
        $examA = $this->examWithAssistants([
            'hora_inicio' => '08:00',
            'duracion' => 90,
        ]);

        $this->assign($examA, $this->mariaId, $this->aulaId)
            ->assertOk();

        // Segundo examen: 08:30 a 10:00, con dos ambientes.
        $examB = $this->createExam([
            'nombre_examen' => 'Segundo examen',
            'fecha' => $examA->fecha->toDateString(),
            'hora_inicio' => '08:30',
            'duracion' => 90,
        ], [$this->aulaId, $this->otraAulaId]);

        $this->enableAssistant($examB, $this->mariaId);

        // No puede controlar otro ambiente al mismo tiempo.
        $this->assign($examB, $this->mariaId, $this->otraAulaId)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['id_ambiente']);

        // El rechazo conserva la asignación anterior.
        $this->assertAssignedTo($examB, $this->mariaId, null);

        // Sí puede controlar ambos exámenes en la misma aula.
        $this->assign($examB, $this->mariaId, $this->aulaId)
            ->assertOk();

        $this->assertAssignedTo($examB, $this->mariaId, $this->aulaId);
    }

    public function test_permite_ambientes_distintos_en_examenes_consecutivos(): void
    {
        // Examen A: 08:00 a 09:30, ambiente 691A.
        $examA = $this->createExam([
            'nombre_examen' => 'Examen de la mañana',
            'hora_inicio' => '08:00',
            'duracion' => 90,
        ], [$this->aulaId]);

        $this->enableAssistant($examA, $this->mariaId);

        $this->assign($examA, $this->mariaId, $this->aulaId)
            ->assertOk();

        // Examen B: comienza exactamente cuando termina A.
        $examB = $this->createExam([
            'nombre_examen' => 'Examen consecutivo',
            'fecha' => $examA->fecha->toDateString(),
            'hora_inicio' => '09:30',
            'duracion' => 90,
        ], [$this->otraAulaId]);

        $this->enableAssistant($examB, $this->mariaId);

        // Puede controlar el segundo examen en otro ambiente.
        $this->assign($examB, $this->mariaId, $this->otraAulaId)
            ->assertOk();

        $this->assertAssignedTo(
            $examB,
            $this->mariaId,
            $this->otraAulaId
        );
    }

    public function test_detecta_conflictos_que_cruzan_medianoche(): void
    {
        // Examen A: comienza a las 23:30 y termina a la 01:00.
        $examA = $this->createExam([
            'nombre_examen' => 'Examen nocturno',
            'fecha' => $this->futureDate(7),
            'hora_inicio' => '23:30',
            'duracion' => 90,
        ], [$this->aulaId]);

        $this->enableAssistant($examA, $this->mariaId);

        $this->assign($examA, $this->mariaId, $this->aulaId)
            ->assertOk();

        // Examen B: empieza al día siguiente, a las 00:15.
        $examB = $this->createExam([
            'nombre_examen' => 'Examen de madrugada',
            'fecha' => $this->futureDate(8),
            'hora_inicio' => '00:15',
            'duracion' => 90,
        ], [$this->otraAulaId]);

        $this->enableAssistant($examB, $this->mariaId);

        // Aunque tienen fechas diferentes, sus horarios coinciden.
        $this->assign($examB, $this->mariaId, $this->otraAulaId)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['id_ambiente']);

        // El rechazo no debe guardar una asignación incompatible.
        $this->assertAssignedTo($examB, $this->mariaId, null);
    }

    private function enableAssistant(Exam $exam, string $userId, ?int $classroomId = null): void
    {
        ExamAssistant::create([
            'id_examen' => $exam->id_examen,
            'id_usuario' => $userId,
            'id_usuario_docente_habilita' => $exam->id_usuario_docente,
            'id_ambiente' => $classroomId,
        ]);
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
