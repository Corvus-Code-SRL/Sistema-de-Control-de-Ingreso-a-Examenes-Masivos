<?php

namespace Tests\Feature\Security;

use App\Models\Exam;
use App\Models\Role;
use App\Models\User;
use App\Services\Security\UserRoleService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsAssistantManagement;
use Tests\Concerns\SeedsSecurityAccounts;
use Tests\TestCase;

/**
 * RNF-02: los endpoints de docente y de administrador exigen el rol vigente de una cuenta ACTIVA.
 * Ya no basta con "no ser auxiliar": un Administrador, una cuenta sin rol o una deshabilitada
 * con token vigente reciben 403.
 */
class RoleChecksTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsAssistantManagement;
    use SeedsSecurityAccounts;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedSecurityAccounts();
        $this->seedAssistantManagement();
    }

    public function test_is_teacher_e_is_administrator_exigen_el_rol_vigente_de_una_cuenta_activa(): void
    {
        $roles = app(UserRoleService::class);

        $teacher = $this->createAccount(Role::DOCENTE);
        $administrator = $this->createAccount(Role::ADMINISTRADOR);
        $assistant = $this->createAccount(Role::AUXILIAR);
        $withoutRole = $this->createAccount();
        $inactiveTeacher = $this->createAccount(Role::DOCENTE, User::ESTADO_INACTIVO);
        $inactiveAdministrator = $this->createAccount(Role::ADMINISTRADOR, User::ESTADO_INACTIVO);
        $closedRole = $this->createAccount(Role::DOCENTE);
        DB::table('usuario_rol')->where('id_usuario', $closedRole->id_usuario)->update(['fecha_fin' => now()]);

        $this->assertTrue($roles->isTeacher($teacher->id_usuario));
        $this->assertFalse($roles->isAdministrator($teacher->id_usuario));

        $this->assertTrue($roles->isAdministrator($administrator->id_usuario));
        $this->assertFalse($roles->isTeacher($administrator->id_usuario));

        foreach ([$assistant, $withoutRole, $inactiveTeacher, $inactiveAdministrator, $closedRole] as $account) {
            $this->assertFalse($roles->isTeacher($account->id_usuario), $account->id_usuario);
        }

        foreach ([$assistant, $withoutRole, $inactiveTeacher, $inactiveAdministrator, $closedRole] as $account) {
            $this->assertFalse($roles->isAdministrator($account->id_usuario), $account->id_usuario);
        }

        $this->assertFalse($roles->isTeacher(null));
        $this->assertFalse($roles->isAdministrator(null));
    }

    public function test_un_docente_con_token_vigente_recibe_403_cuando_la_cuenta_se_deshabilita(): void
    {
        $teacher = $this->createAccount(Role::DOCENTE);
        $token = $this->loginToken($teacher);

        $this->withBearer($token)
            ->postJson('/api/grupos', $this->groupPayload('TOK1'))
            ->assertStatus(201);

        $teacher->forceFill(['estado' => User::ESTADO_INACTIVO])->save();

        $this->withBearer($token)
            ->postJson('/api/grupos', $this->groupPayload('TOK2'))
            ->assertForbidden()
            ->assertJsonPath('message', 'Solo un docente puede gestionar grupos.');

        $this->assertDatabaseMissing('grupo', ['num_grupo' => 'TOK2']);
    }

    public function test_un_administrador_con_token_vigente_recibe_403_cuando_la_cuenta_se_deshabilita(): void
    {
        $administrator = $this->createAccount(Role::ADMINISTRADOR);
        $token = $this->loginToken($administrator);

        $this->withBearer($token)->getJson('/api/roles')->assertOk();
        $this->assertNotSame(403, $this->withBearer($token)->postJson('/api/materias', [])->getStatusCode());

        $administrator->forceFill(['estado' => User::ESTADO_INACTIVO])->save();

        // Request (AdministratorRequest, StoreUserRequest, VerifySisRequest) y Policy (materias).
        $this->withBearer($token)->getJson('/api/roles')->assertForbidden();
        $this->withBearer($token)->getJson('/api/sis/verificar/202312345')->assertForbidden();
        $this->withBearer($token)->postJson('/api/usuarios', [])->assertForbidden();
        $this->withBearer($token)->postJson('/api/materias', [])->assertForbidden();
        $this->withBearer($token)->getJson('/api/materias/administracion')->assertForbidden();
    }

    public function test_solo_un_docente_gestiona_grupos_y_examenes(): void
    {
        $exam = Exam::findOrFail($this->examId);

        $outsiders = [
            'administrador' => $this->createAccount(Role::ADMINISTRADOR),
            'auxiliar' => $this->createAccount(Role::AUXILIAR),
            'sin rol' => $this->createAccount(),
            'docente inactivo' => $this->createAccount(Role::DOCENTE, User::ESTADO_INACTIVO),
        ];

        foreach ($outsiders as $label => $account) {
            $this->actAsUserId($account->id_usuario);

            $this->postJson('/api/grupos', $this->groupPayload('NO'))->assertForbidden();
            $this->postJson('/api/examenes', $this->validExamPayload())->assertForbidden();
            $this->putJson("/api/examenes/{$exam->id_examen}", $this->validExamPayload())->assertForbidden();
            $this->postJson("/api/examenes/{$exam->id_examen}/cancelar")->assertForbidden();
            $this->postJson("/api/examenes/{$exam->id_examen}/finalizar")->assertForbidden();
            $this->postJson("/api/examenes/{$exam->id_examen}/grupos", ['grupos' => [$this->grupoPropioId]])
                ->assertForbidden();
            $this->postJson("/api/grupos/{$this->grupoPropioId}/nomina/preview")->assertForbidden();
        }

        $this->assertDatabaseMissing('grupo', ['num_grupo' => 'NO']);
        $this->assertSame(Exam::PROGRAMADO, $exam->fresh()->estado);
    }

    public function test_el_docente_dueno_si_puede_cancelar_su_examen(): void
    {
        $this->actAsTeacher($this->docenteId);

        $this->postJson("/api/examenes/{$this->examId}/cancelar")
            ->assertOk()
            ->assertJsonPath('data.estado', Exam::CANCELADO);
    }

    public function test_solo_un_administrador_registra_cuentas_y_verifica_codigos_sis(): void
    {
        $outsiders = [
            $this->createAccount(Role::DOCENTE),
            $this->createAccount(Role::AUXILIAR),
            $this->createAccount(),
            $this->createAccount(Role::ADMINISTRADOR, User::ESTADO_INACTIVO),
        ];

        foreach ($outsiders as $account) {
            $this->actAsUserId($account->id_usuario);

            $this->postJson('/api/usuarios', [
                'cod_sis' => '202312345',
                'nombre' => 'Juan',
                'apellido_paterno' => 'Perez',
                'correo' => 'juan.perez@est.umss.edu',
            ])->assertForbidden()->assertJsonPath('message', 'Solo un Administrador puede registrar cuentas.');

            $this->getJson('/api/sis/verificar/202312345')
                ->assertForbidden()
                ->assertJsonPath('message', 'Solo un Administrador puede verificar códigos SIS.');
        }

        $this->assertDatabaseMissing('usuario', ['cod_sis' => '202312345']);

        $this->actAsUserId($this->administratorId);

        $this->getJson('/api/sis/verificar/202312345')->assertOk();
    }

    private function groupPayload(string $number): array
    {
        return [
            'id_carrera' => $this->sistemasId,
            'id_materia' => $this->calculoId,
            'num_grupo' => $number,
        ];
    }

    private function validExamPayload(): array
    {
        return [
            'nombre_examen' => 'Parcial nuevo',
            'id_carrera' => $this->sistemasId,
            'id_materia' => $this->calculoId,
            'fecha' => now()->addWeek()->toDateString(),
            'hora_inicio' => '08:00',
            'duracion' => 90,
            'ambientes' => [1],
            'grupos' => [$this->grupoPropioId],
        ];
    }

    /** Inicia sesión por la API, como en producción, y devuelve el token. */
    private function loginToken(User $account): string
    {
        $this->actAsGuest();

        return $this->postJson('/api/auth/login', ['cod_sis' => $account->cod_sis, 'password' => 'password'])
            ->assertOk()
            ->json('data.token');
    }

    /** Cada petición con token resuelve la sesión desde cero, como en producción. */
    private function withBearer(string $token): self
    {
        $this->actAsGuest();

        return $this->withToken($token);
    }
}
