<?php

namespace Tests\Feature\Exams;

use App\Models\Exam;
use App\Models\ExamInvitation;
use App\Models\Role;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\AssignsIncidentRole;
use Tests\Concerns\BuildsRosterFiles;
use Tests\Concerns\SeedsCollaboratorInvitations;
use Tests\Concerns\SeedsExamCatalog;
use Tests\TestCase;

/**
 * Capacidad propia de la carga de habilitados (HU-028): eligible_list.
 */
class EligibleListCapabilityTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsExamCatalog;
    use SeedsCollaboratorInvitations;
    use AssignsIncidentRole;
    use BuildsRosterFiles;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedExamCatalog();
    }

    public function test_auxiliar_y_administrador_reciben_403_en_todos_los_endpoints(): void
    {
        $exam = $this->examWithRoster();

        foreach ([Role::AUXILIAR, Role::ADMINISTRADOR] as $i => $role) {
            $actorId = $this->createTeacherAccount('Cuenta', 'Prueba', '2099000' . $i, null, ['rol' => $role]);
            $this->actAsUserId($actorId);

            $this->getJson('/api/examenes/habilitados')->assertForbidden();
            $this->getJson("/api/examenes/{$exam->id_examen}/habilitados")->assertForbidden();
            $this->postJson("/api/examenes/{$exam->id_examen}/habilitados/preview")->assertForbidden();
            $this->postJson("/api/examenes/{$exam->id_examen}/habilitados/confirm", ['token' => 'x'])->assertForbidden();
        }
    }

    public function test_docente_sin_la_capacidad_recibe_403_aunque_sea_el_creador(): void
    {
        $exam = $this->examWithRoster();
        $this->actAsTeacher($this->docenteId);
        config(['sciem.access.eligible_list' => [Role::ADMINISTRADOR]]);

        $this->getJson('/api/examenes/habilitados')->assertForbidden();
        $this->getJson("/api/examenes/{$exam->id_examen}/habilitados")->assertForbidden();
        $this->postJson("/api/examenes/{$exam->id_examen}/habilitados/preview")->assertForbidden();
        $this->postJson("/api/examenes/{$exam->id_examen}/habilitados/confirm", ['token' => 'x'])->assertForbidden();
    }

    public function test_invitado_creador_carga_la_lista(): void
    {
        $exam = $this->examWithRoster();
        $guestId = $this->createTeacherAccount('Docente', 'Invitado', 'INV028', null, ['rol' => Role::INVITADO]);
        $exam->forceFill(['id_usuario_docente' => $guestId])->save();
        $this->actAsUserId($guestId);

        $this->getJson('/api/examenes/habilitados')->assertOk();
        $this->getJson("/api/examenes/{$exam->id_examen}/habilitados")->assertOk();

        $sis = DB::table('estudiante')
            ->join('grupo_estudiante', 'grupo_estudiante.id_estudiante', '=', 'estudiante.id_estudiante')
            ->where('grupo_estudiante.id_grupo', $this->grupoPropioId)
            ->orderBy('estudiante.id_estudiante')
            ->value('estudiante.cod_sis');

        $token = $this->post(
            "/api/examenes/{$exam->id_examen}/habilitados/preview",
            ['archivo' => $this->csvFile([[(string) $sis, 'APELLIDO', 'NOMBRE']])],
            ['Accept' => 'application/json']
        )->assertOk()->json('data.token');

        $this->postJson("/api/examenes/{$exam->id_examen}/habilitados/confirm", ['token' => $token])->assertOk();
    }

    public function test_colaborador_aceptado_recibe_403_en_lectura_y_escritura(): void
    {
        $exam = $this->examWithRoster();
        $this->createInvitation($exam, $this->otroDocenteId, ExamInvitation::ACEPTADA);
        $this->actAsTeacher($this->otroDocenteId);

        $this->getJson("/api/examenes/{$exam->id_examen}/habilitados")->assertForbidden();
        $this->post(
            "/api/examenes/{$exam->id_examen}/habilitados/preview",
            ['archivo' => $this->csvFile([['300000001', 'APELLIDO', 'NOMBRE']])],
            ['Accept' => 'application/json']
        )->assertForbidden();
        $this->postJson("/api/examenes/{$exam->id_examen}/habilitados/confirm", ['token' => str_repeat('a', 64)])
            ->assertForbidden();
    }

    public function test_ya_no_existe_el_endpoint_manual(): void
    {
        $exam = $this->examWithRoster();
        $this->actAsTeacher($this->docenteId);

        $this->putJson("/api/examenes/{$exam->id_examen}/habilitados", ['cambios' => []])
            ->assertStatus(405);
    }

    private function examWithRoster(): Exam
    {
        $exam = $this->createExam();
        DB::table('grupo_examen')->insertOrIgnore([
            'id_examen' => $exam->id_examen,
            'id_grupo' => $this->grupoPropioId,
        ]);

        return $exam;
    }
}
