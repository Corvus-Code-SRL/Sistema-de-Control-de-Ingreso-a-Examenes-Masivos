<?php

namespace Tests\Feature\Academic;

use App\Models\Action;
use App\Models\AuditLog;
use App\Models\Exam;
use App\Models\Group;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use App\Support\RecordStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsRosterFiles;
use Tests\Concerns\SeedsAssistantManagement;
use Tests\TestCase;

/**
 * HU-023: registrar un estudiante excepcional en un grupo propio.
 *
 * Solo el Docente dueño del grupo (capacidad special_cases). El estudiante entra a grupo_estudiante con la marca
 * «Excepcional» y su motivo; con un examen del grupo en ingreso o en curso se rechaza (409); una
 * nueva carga de nómina (021) no lo borra.
 */
class ExceptionalStudentTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsAssistantManagement;
    use BuildsRosterFiles;

    private const SIS = '202600777';

    private const GROUPS_URL = '/api/docente/estudiantes-excepcionales/grupos';

    private const FORBIDDEN_MESSAGE = 'Solo un docente puede registrar estudiantes excepcionales.';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAssistantManagement();
    }

    /* ------------------------------- ayudantes ------------------------------- */

    private function searchUrl(int $groupId, string $sis = self::SIS): string
    {
        return "/api/docente/grupos/{$groupId}/estudiantes-excepcionales/buscar?cod_sis={$sis}";
    }

    private function storeUrl(int $groupId): string
    {
        return "/api/docente/grupos/{$groupId}/estudiantes-excepcionales";
    }

    /** Un estudiante del SIS que no figura en ninguna nómina. */
    private function createStudent(string $sis = self::SIS): int
    {
        return (int) DB::table('estudiante')->insertGetId([
            'cod_sis' => $sis,
            'ci' => '9876543',
            'nombre' => 'Ana',
            'apellido_paterno' => 'Quispe',
            'apellido_materno' => 'Mamani',
            'estado' => RecordStatus::ACTIVE,
        ], 'id_estudiante');
    }

    private function createInvitedTeacher(): string
    {
        $user = User::create([
            'nombre' => 'Ines',
            'apellido_paterno' => 'Invitada',
            'correo' => 'ines.invitada@test.com',
            'contrasenia' => 'x',
            'cod_sis' => '202400099',
            'estado' => RecordStatus::ACTIVE,
        ]);

        $role = Role::firstOrCreate(
            ['nombre_rol' => Role::INVITADO],
            ['descripcion' => 'Docente invitado', 'estado' => RecordStatus::ACTIVE]
        );

        UserRole::create([
            'id_usuario' => $user->id_usuario,
            'id_rol' => $role->id_rol,
            'fecha_inicio' => now(),
        ]);

        return $user->id_usuario;
    }

    private function register(int $studentId, array $overrides = [], ?int $groupId = null)
    {
        return $this->postJson(
            $this->storeUrl($groupId ?? $this->grupoPropioId),
            array_merge([
                'id_estudiante' => $studentId,
                'motivo' => 'Convalidación aprobada por la Dirección de Carrera',
            ], $overrides)
        );
    }

    private function exceptionalCount(): int
    {
        return DB::table('grupo_estudiante')->where('es_excepcional', true)->count();
    }

    /* --------------------------- grupos del docente --------------------------- */

    public function test_el_docente_lista_sus_grupos_con_sus_carreras(): void
    {
        $response = $this->getJson(self::GROUPS_URL)->assertOk();

        $ids = array_column($response->json('data'), 'id_grupo');
        $otherTeacherGroup = (int) Group::where('id_usuario_docente', $this->otroDocenteId)->value('id_grupo');

        $this->assertContains($this->grupoPropioId, $ids);
        $this->assertContains($this->segundoGrupoPropioId, $ids);
        $this->assertNotContains($otherTeacherGroup, $ids);

        $own = collect($response->json('data'))->firstWhere('id_grupo', $this->grupoPropioId);
        $this->assertFalse($own['bloqueado']);
        $this->assertSame([$this->sistemasId], array_column($own['carreras'], 'id_carrera'));
    }

    public function test_un_grupo_con_examen_en_ingreso_sale_como_bloqueado(): void
    {
        $this->createExamLinkedTo([$this->grupoPropioId], Exam::EN_INGRESO);

        $own = collect($this->getJson(self::GROUPS_URL)->assertOk()->json('data'))
            ->firstWhere('id_grupo', $this->grupoPropioId);

        $this->assertTrue($own['bloqueado']);
    }

    /* -------------------------------- búsqueda -------------------------------- */

    public function test_busca_al_estudiante_por_codigo_sis(): void
    {
        $studentId = $this->createStudent();

        $this->getJson($this->searchUrl($this->grupoPropioId))
            ->assertOk()
            ->assertJsonPath('data.id_estudiante', $studentId)
            ->assertJsonPath('data.cod_sis', self::SIS)
            ->assertJsonPath('data.nombre_completo', 'Ana Quispe Mamani')
            ->assertJsonPath('data.ci', '9876543')
            ->assertJsonPath('data.ya_inscrito', false)
            ->assertJsonPath('data.carrera', null);
    }

    public function test_la_busqueda_muestra_la_carrera_de_su_inscripcion_mas_reciente(): void
    {
        $studentId = $this->createStudent();

        DB::table('grupo_estudiante')->insert([
            'id_grupo' => $this->segundoGrupoPropioId,
            'id_estudiante' => $studentId,
            'fecha_inscripcion' => now()->toDateString(),
            'estado' => RecordStatus::ACTIVE,
            'id_carrera' => $this->sistemasId,
        ]);

        $this->getJson($this->searchUrl($this->grupoPropioId))
            ->assertOk()
            ->assertJsonPath('data.carrera.id_carrera', $this->sistemasId)
            ->assertJsonPath('data.ya_inscrito', false);
    }

    public function test_la_busqueda_avisa_si_ya_esta_inscrito_en_el_grupo(): void
    {
        $this->enrollStudentWithSis($this->grupoPropioId, self::SIS);

        $this->getJson($this->searchUrl($this->grupoPropioId))
            ->assertOk()
            ->assertJsonPath('data.ya_inscrito', true);
    }

    public function test_un_codigo_que_no_existe_responde_404_con_el_mensaje_del_criterio(): void
    {
        $this->getJson($this->searchUrl($this->grupoPropioId, '209999999'))
            ->assertNotFound()
            ->assertJsonPath('message', 'No se encontró a ningún estudiante con este código');
    }

    public function test_la_busqueda_exige_el_codigo(): void
    {
        $this->getJson("/api/docente/grupos/{$this->grupoPropioId}/estudiantes-excepcionales/buscar")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['cod_sis']);
    }

    /* -------------------------------- registro -------------------------------- */

    public function test_registra_al_estudiante_con_la_marca_y_el_motivo(): void
    {
        $studentId = $this->createStudent();

        $this->register($studentId)
            ->assertCreated()
            ->assertJsonPath('data.id_estudiante', $studentId)
            ->assertJsonPath('data.es_excepcional', true)
            ->assertJsonPath('data.motivo_excepcional', 'Convalidación aprobada por la Dirección de Carrera')
            ->assertJsonPath('data.carrera.id_carrera', $this->sistemasId)
            ->assertJsonPath('mensaje', 'Estudiante excepcional registrado.');

        $row = DB::table('grupo_estudiante')
            ->where('id_grupo', $this->grupoPropioId)
            ->where('id_estudiante', $studentId)
            ->first();

        $this->assertNotNull($row);
        $this->assertTrue((bool) $row->es_excepcional);
        $this->assertSame('Convalidación aprobada por la Dirección de Carrera', $row->motivo_excepcional);
        $this->assertSame(RecordStatus::ACTIVE, $row->estado);
        $this->assertSame($this->sistemasId, (int) $row->id_carrera);
    }

    public function test_el_registro_queda_en_la_bitacora(): void
    {
        $studentId = $this->createStudent();
        $actionId = (int) Action::where('operacion', 'REGISTRAR_ESTUDIANTE_EXCEPCIONAL')->value('id_accion');

        $this->register($studentId)->assertCreated();

        $this->assertSame(1, AuditLog::query()
            ->where('id_accion', $actionId)
            ->where('tabla_afectada', 'grupo_estudiante')
            ->where('id_usuario', $this->docenteId)
            ->count());
    }

    public function test_con_el_examen_programado_si_se_permite(): void
    {
        $this->assertSame(
            Exam::PROGRAMADO,
            DB::table('examen')->where('id_examen', $this->examId)->value('estado')
        );

        $this->register($this->createStudent())->assertCreated();

        $this->assertSame(1, $this->exceptionalCount());
    }

    /**
     * @dataProvider lockingExamStates
     */
    public function test_con_un_examen_en_ingreso_o_en_curso_responde_409_y_no_escribe(string $state): void
    {
        $this->createExamLinkedTo([$this->grupoPropioId], $state);
        $studentId = $this->createStudent();

        $this->register($studentId)
            ->assertStatus(409)
            ->assertJsonPath(
                'message',
                'No se puede registrar un estudiante excepcional mientras el grupo '
                . 'tiene un examen en ingreso o en curso.'
            );

        $this->assertSame(0, $this->exceptionalCount());
        $this->assertFalse(
            DB::table('grupo_estudiante')->where('id_estudiante', $studentId)->exists()
        );
    }

    public function lockingExamStates(): array
    {
        return [
            'EN_INGRESO' => [Exam::EN_INGRESO],
            'EN_CURSO' => [Exam::EN_CURSO],
        ];
    }

    public function test_el_motivo_es_obligatorio(): void
    {
        $studentId = $this->createStudent();

        foreach ([null, '', '   '] as $motivo) {
            $this->register($studentId, ['motivo' => $motivo])
                ->assertStatus(422)
                ->assertJsonPath('errors.motivo.0', 'Indique el motivo del registro excepcional');
        }

        $this->assertSame(0, $this->exceptionalCount());
    }

    public function test_el_motivo_no_puede_pasar_de_255_caracteres(): void
    {
        $this->register($this->createStudent(), ['motivo' => str_repeat('a', 256)])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['motivo']);

        $this->assertSame(0, $this->exceptionalCount());
    }

    public function test_no_registra_dos_veces_al_mismo_estudiante(): void
    {
        $studentId = $this->createStudent();

        $this->register($studentId)->assertCreated();

        $this->register($studentId)
            ->assertStatus(422)
            ->assertJsonPath('errors.id_estudiante.0', 'Ya está inscrito en este grupo');

        $this->assertSame(1, $this->exceptionalCount());
    }

    public function test_un_estudiante_de_la_nomina_ya_esta_inscrito(): void
    {
        $this->enrollStudentWithSis($this->grupoPropioId, self::SIS);
        $studentId = (int) DB::table('estudiante')->where('cod_sis', self::SIS)->value('id_estudiante');

        $this->register($studentId)
            ->assertStatus(422)
            ->assertJsonPath('errors.id_estudiante.0', 'Ya está inscrito en este grupo');

        $this->assertSame(0, $this->exceptionalCount());
    }

    public function test_un_estudiante_inexistente_se_rechaza(): void
    {
        $this->register(999999)
            ->assertStatus(422)
            ->assertJsonPath('errors.id_estudiante.0', 'No se encontró a ningún estudiante con este código');
    }

    public function test_una_carrera_que_el_grupo_no_atiende_se_rechaza(): void
    {
        $this->register($this->createStudent(), ['id_carrera' => 999999])
            ->assertStatus(422)
            ->assertJsonPath('errors.id_carrera.0', 'La carrera elegida no la atiende el grupo.');

        $this->assertSame(0, $this->exceptionalCount());
    }

    /* ---------------------- nómina (021) no borra excepcionales ---------------------- */

    public function test_una_nueva_carga_de_nomina_no_borra_al_excepcional(): void
    {
        $studentId = $this->createStudent();
        $this->register($studentId)->assertCreated();

        $preview = $this->previewRoster($this->csvFile($this->rosterRows(2)), $this->grupoPropioId);
        $preview->assertOk();
        $this->confirmRoster($preview->json('data.token'), $this->grupoPropioId)->assertOk();

        $row = DB::table('grupo_estudiante')
            ->where('id_grupo', $this->grupoPropioId)
            ->where('id_estudiante', $studentId)
            ->first();

        $this->assertNotNull($row);
        $this->assertTrue((bool) $row->es_excepcional);
        $this->assertSame('Convalidación aprobada por la Dirección de Carrera', $row->motivo_excepcional);
        $this->assertSame(RecordStatus::ACTIVE, $row->estado);
    }

    /* --------------------------------- permisos --------------------------------- */

    public function test_otro_docente_no_puede_registrar_en_un_grupo_ajeno(): void
    {
        $studentId = $this->createStudent();
        $otherGroupId = (int) Group::where('id_usuario_docente', $this->otroDocenteId)->value('id_grupo');

        $this->register($studentId, [], $otherGroupId)->assertForbidden();
        $this->getJson($this->searchUrl($otherGroupId))->assertForbidden();

        $this->assertSame(0, $this->exceptionalCount());
    }

    public function test_un_grupo_inexistente_responde_404(): void
    {
        $this->register($this->createStudent(), [], 999999)->assertNotFound();
        $this->getJson($this->searchUrl(999999))->assertNotFound();
    }

    public function test_un_id_de_grupo_invalido_responde_404_sin_llegar_a_la_base(): void
    {
        $this->getJson('/api/docente/grupos/abc/estudiantes-excepcionales/buscar?cod_sis=1')->assertNotFound();
        $this->postJson('/api/docente/grupos/1234567890/estudiantes-excepcionales', [])->assertNotFound();
    }

    public function test_el_invitado_recibe_403_y_no_se_escribe_nada(): void
    {
        $studentId = $this->createStudent();
        $this->actAsUserId($this->createInvitedTeacher());

        $this->getJson(self::GROUPS_URL)->assertForbidden()->assertJsonPath('message', self::FORBIDDEN_MESSAGE);
        $this->getJson($this->searchUrl($this->grupoPropioId))->assertForbidden();
        $this->register($studentId)->assertForbidden()->assertJsonPath('message', self::FORBIDDEN_MESSAGE);

        $this->assertSame(0, $this->exceptionalCount());
    }

    public function test_un_auxiliar_recibe_403_y_no_se_escribe_nada(): void
    {
        $studentId = $this->createStudent();
        $this->actAsUserId($this->createAssistant('Beto', '202400010'));

        $this->getJson(self::GROUPS_URL)->assertForbidden();
        $this->register($studentId)->assertForbidden()->assertJsonPath('message', self::FORBIDDEN_MESSAGE);

        $this->assertSame(0, $this->exceptionalCount());
    }

    public function test_sin_sesion_responde_401(): void
    {
        $this->actAsGuest();

        $this->getJson(self::GROUPS_URL)->assertUnauthorized();
        $this->getJson($this->searchUrl($this->grupoPropioId))->assertUnauthorized();
        $this->postJson($this->storeUrl($this->grupoPropioId), [])->assertUnauthorized();
    }
}
