<?php

namespace Tests\Feature\Exams\RoomDistribution;

use App\Models\ExamInvitation;
use App\Models\Role;
use App\Services\Exams\Distribution\DistributionVersion;
use App\Services\Exams\Distribution\RoomDistributionSaveService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsRoomDistribution;
use Tests\TestCase;

class RoomDistributionPermissionsTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsRoomDistribution;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedExamCatalog();
    }

    public function test_creador_guarda_y_colaborador_aceptado_solo_lee(): void
    {
        $exam = $this->distributionExam();
        $this->createInvitation($exam, $this->otroDocenteId, ExamInvitation::ACEPTADA);
        $saved = $this->saveDistribution($exam)->assertOk()->json('data');
        $assignments = array_map(fn (array $s): array => [
            'id_estudiante' => $s['id_estudiante'], 'id_ambiente' => $s['id_ambiente'],
        ], $saved['estudiantes']);
        $this->actAsTeacher($this->otroDocenteId);

        $this->getJson($this->distributionUrl($exam))->assertOk()->assertJsonPath('data.editable', false);
        $this->postJson($this->distributionUrl($exam) . '/propuesta')->assertForbidden();
        $this->putJson($this->distributionUrl($exam), [
            'version' => $saved['version'], 'asignaciones' => $assignments,
        ])->assertForbidden();
    }

    public function test_ajeno_o_invitado_pendiente_no_puede_leer_ni_modificar(): void
    {
        $exam = $this->distributionExam();
        $this->createInvitation($exam, $this->otroDocenteId);
        $this->actAsTeacher($this->otroDocenteId);

        $this->getJson($this->distributionUrl($exam))->assertForbidden();
        $this->postJson($this->distributionUrl($exam) . '/propuesta')->assertForbidden();
        $this->putJson($this->distributionUrl($exam), [
            'version' => DistributionVersion::fromRows([]), 'asignaciones' => [],
        ])->assertForbidden();
    }

    /** @dataProvider nonTeachingRoles */
    public function test_roles_sin_docencia_no_acceden_a_ningun_endpoint(string $role): void
    {
        $exam = $this->distributionExam();
        $actorId = $this->createTeacherAccount('Cuenta', 'Prueba', '209900001', null, ['rol' => $role]);
        $this->actAsUserId($actorId);

        $this->getJson('/api/examenes/distribucion')->assertForbidden();
        $this->getJson($this->distributionUrl($exam))->assertForbidden();
        $this->postJson($this->distributionUrl($exam) . '/propuesta')->assertForbidden();
        $this->putJson($this->distributionUrl($exam), [
            'version' => DistributionVersion::fromRows([]), 'asignaciones' => [],
        ])->assertForbidden();
    }

    /** @return array<string, array{0:string}> */
    public function nonTeachingRoles(): array
    {
        return ['auxiliar' => [Role::AUXILIAR], 'administrador' => [Role::ADMINISTRADOR]];
    }

    public function test_docente_invitado_creador_puede_distribuir_segun_teaching(): void
    {
        $exam = $this->distributionExam();
        $guestId = $this->createTeacherAccount('Docente', 'Invitado', 'INV031', null, ['rol' => Role::INVITADO]);
        $exam->forceFill(['id_usuario_docente' => $guestId])->save();
        $this->actAsUserId($guestId);

        $this->getJson('/api/examenes/distribucion')->assertOk()->assertJsonPath('data.0.editable', true);
        $this->saveDistribution($exam)->assertOk();
    }

    public function test_la_capacidad_propia_decide_el_acceso_aunque_el_rol_ejerza_la_docencia(): void
    {
        $exam = $this->distributionExam();
        config(['sciem.access.room_distribution' => [Role::ADMINISTRADOR]]);

        $this->getJson('/api/examenes/distribucion')->assertForbidden();
        $this->getJson($this->distributionUrl($exam))->assertForbidden();
        $this->postJson($this->distributionUrl($exam) . '/propuesta')->assertForbidden();
        $this->putJson($this->distributionUrl($exam), [
            'version' => DistributionVersion::fromRows([]), 'asignaciones' => [],
        ])->assertForbidden();
    }

    public function test_inexistente_devuelve_404_en_lectura_propuesta_y_guardado(): void
    {
        $url = '/api/examenes/999999999/distribucion';
        $this->getJson($url)->assertNotFound();
        $this->postJson($url . '/propuesta')->assertNotFound();
        $this->putJson($url, ['version' => DistributionVersion::fromRows([]), 'asignaciones' => []])->assertNotFound();
    }

    public function test_sin_token_los_cuatro_endpoints_devuelven_401(): void
    {
        $exam = $this->distributionExam();
        $this->actAsGuest();

        $this->getJson('/api/examenes/distribucion')->assertUnauthorized();
        $this->getJson($this->distributionUrl($exam))->assertUnauthorized();
        $this->postJson($this->distributionUrl($exam) . '/propuesta')->assertUnauthorized();
        $this->putJson($this->distributionUrl($exam))->assertUnauthorized();
    }

    public function test_el_service_revalida_el_rol_sin_depender_del_form_request(): void
    {
        $exam = $this->distributionExam();
        $assignments = $this->distributionProposal($exam);
        DB::table('usuario_rol')->where('id_usuario', $this->docenteId)->update(['fecha_fin' => now()]);

        $this->expectException(AuthorizationException::class);
        app(RoomDistributionSaveService::class)->save(
            (int) $exam->id_examen, DistributionVersion::fromRows([]), $assignments, $this->docenteId
        );
    }
}
