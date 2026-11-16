<?php

namespace Tests\Feature\Exams\CollaboratorInvitations;

use App\Models\Role;
use App\Services\Security\UserRoleService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\AssignsIncidentRole;
use Tests\Concerns\SeedsCollaboratorInvitations;
use Tests\Concerns\SeedsExamCatalog;
use Tests\TestCase;

/** HU-026: invitar colaboradores es una función del Docente (config sciem.access.invite_teachers). */
class CollaboratorInvitationAccessTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsExamCatalog;
    use AssignsIncidentRole;
    use SeedsCollaboratorInvitations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedExamCatalog();
    }

    /** @return array<string, array{0: string, 1: string}> */
    private function endpoints(): array
    {
        $exam = $this->createExam();
        $url = $this->invitationsUrl($exam);

        return [
            'exámenes' => ['getJson', '/api/docente/invitaciones/examenes'],
            'búsqueda' => ['getJson', $url . '/docentes?criterio=vargas'],
            'envío' => ['postJson', $url],
            'enviadas' => ['getJson', '/api/docente/invitaciones'],
        ];
    }

    private function actAsRole(string $role): void
    {
        $id = $this->createTeacherAccount('Cuenta', $role, '200500001', null, ['rol' => $role]);
        $this->actAsTeacher($id);
    }

    public function test_el_docente_ejerce_la_funcion_y_el_invitado_no(): void
    {
        $roles = app(UserRoleService::class);
        $guest = $this->createTeacherAccount('Gus', 'Invitado', 'INV1234', null, ['rol' => Role::INVITADO]);

        $this->assertTrue($roles->canUse($this->docenteId, 'invite_teachers'));
        $this->assertFalse($roles->canUse($guest, 'invite_teachers'));
    }

    /** @dataProvider forbiddenRoles */
    public function test_los_demas_roles_reciben_403_en_todas_las_rutas(string $role): void
    {
        $endpoints = $this->endpoints();
        $this->actAsRole($role);

        foreach ($endpoints as $label => [$method, $url]) {
            $this->{$method}($url, $method === 'postJson' ? ['id_docente' => $this->otroDocenteId] : [])
                ->assertForbidden();
        }
    }

    public static function forbiddenRoles(): array
    {
        return [
            'invitado' => [Role::INVITADO],
            'auxiliar' => [Role::AUXILIAR],
            'administrador' => [Role::ADMINISTRADOR],
        ];
    }

    public function test_una_cuenta_sin_rol_vigente_recibe_403(): void
    {
        $endpoints = $this->endpoints();
        DB::table('usuario_rol')->where('id_usuario', $this->docenteId)->update(['fecha_fin' => now()]);

        foreach ($endpoints as [$method, $url]) {
            $this->{$method}($url)->assertForbidden();
        }
    }

    public function test_sin_sesion_recibe_401(): void
    {
        $endpoints = $this->endpoints();
        $this->actAsGuest();

        foreach ($endpoints as [$method, $url]) {
            $this->{$method}($url)->assertUnauthorized();
        }
    }
}
