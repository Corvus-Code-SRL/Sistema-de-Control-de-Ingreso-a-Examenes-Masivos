<?php

namespace Tests\Feature\Academic;

use App\Models\Exam;
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
 * HU-023: registrar un estudiante excepcional en UN examen propio.
 *
 * Solo el Docente dueño del examen (capacidad special_cases). El estudiante se agrega a la lista de
 * ese examen y a ningún otro: no entra al padrón del grupo ni a la nómina.
 *
 * Los casos que dependen de dónde se guarda el registro (decisión 1 con el líder) están marcados
 * como incompletos al final; se completan cuando exista el almacenamiento.
 */
class ExceptionalStudentTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsAssistantManagement;
    use BuildsRosterFiles;

    private const SIS = '202600777';

    private const EXAMS_URL = '/api/docente/estudiantes-excepcionales/examenes';

    private const FORBIDDEN_MESSAGE = 'Solo un docente puede registrar estudiantes excepcionales.';

    private const REASON = 'Convalidación aprobada por la Dirección de Carrera';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAssistantManagement();

        // El examen base del escenario es del docente que actúa.
        DB::table('examen')
            ->where('id_examen', $this->examId)
            ->update(['id_usuario_docente' => $this->docenteId]);
    }

    /* ------------------------------- ayudantes ------------------------------- */

    private function searchUrl(int $examId, string $sis = self::SIS): string
    {
        return "/api/docente/examenes/{$examId}/estudiantes-excepcionales/buscar?cod_sis={$sis}";
    }

    private function storeUrl(int $examId): string
    {
        return "/api/docente/examenes/{$examId}/estudiantes-excepcionales";
    }

    /** Un estudiante de los registros de SCIEM que no figura en ninguna nómina. */
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

    /** Un examen del docente que actúa, en el estado dado, vinculado a los grupos indicados. */
    private function createOwnExam(string $state, array $groupIds): int
    {
        $before = DB::table('examen')->pluck('id_examen')->all();

        $this->createExamLinkedTo($groupIds, $state);

        $created = array_values(array_diff(DB::table('examen')->pluck('id_examen')->all(), $before));
        $examId = (int) end($created);

        DB::table('examen')->where('id_examen', $examId)->update(['id_usuario_docente' => $this->docenteId]);

        return $examId;
    }

    /** El examen base pasa a ser de otro docente. */
    private function makeExamForeign(int $examId): void
    {
        DB::table('examen')->where('id_examen', $examId)->update(['id_usuario_docente' => $this->otroDocenteId]);
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

    private function register(int $studentId, array $overrides = [], ?int $examId = null)
    {
        $targetExam = $examId ?? $this->examId;
        $groupId = (int) (DB::table('grupo_examen')->where('id_examen', $targetExam)->value('id_grupo') ?: $this->grupoPropioId);
        $careerId = (int) (DB::table('grupo_oferta')->where('id_grupo', $groupId)->value('id_carrera') ?: $this->sistemasId);
        // Identificador positivo solo para pruebas negativas: evita que el Request
        // devuelva 422 por min:1 antes de comprobar permisos, existencia o estado.
        // No sirve como fixture de un registro exitoso.
        $roomId = (int) (DB::table('examen_ambiente')->where('id_examen', $targetExam)->value('id_ambiente') ?: 1);

        return $this->postJson(
            $this->storeUrl($targetExam),
            array_merge([
                'id_estudiante' => $studentId,
                'id_grupo' => $groupId,
                'id_carrera' => $careerId,
                'id_ambiente' => $roomId,
                'motivo' => self::REASON,
            ], $overrides)
        );
    }

    /* --------------------------- exámenes del docente --------------------------- */

    public function test_el_docente_lista_sus_examenes_con_el_aviso_de_si_admiten_excepcionales(): void
    {
        $inProgress = $this->createOwnExam(Exam::EN_CURSO, [$this->grupoPropioId]);

        $data = collect($this->getJson(self::EXAMS_URL)->assertOk()->json('data'));

        $programmed = $data->firstWhere('id_examen', $this->examId);
        $running = $data->firstWhere('id_examen', $inProgress);

        $this->assertNotNull($programmed);
        $this->assertSame(Exam::PROGRAMADO, $programmed['estado']);
        $this->assertFalse($programmed['permitido']); // Control de ingreso aún cerrado.

        $this->assertNotNull($running);
        $this->assertSame(Exam::EN_CURSO, $running['estado']);
        $this->assertFalse($running['permitido']);
    }

    public function test_no_lista_los_examenes_de_otro_docente(): void
    {
        $this->makeExamForeign($this->examId);

        $ids = array_column($this->getJson(self::EXAMS_URL)->assertOk()->json('data'), 'id_examen');

        $this->assertNotContains($this->examId, $ids);
    }

    /* -------------------------------- búsqueda -------------------------------- */

    public function test_busca_al_estudiante_por_codigo_sis_mostrando_el_ci(): void
    {
        $studentId = $this->createStudent();

        $response = $this->getJson($this->searchUrl($this->examId))
            ->assertOk()
            ->assertJsonPath('data.id_estudiante', $studentId)
            ->assertJsonPath('data.cod_sis', self::SIS)
            ->assertJsonPath('data.nombre_completo', 'Ana Quispe Mamani')
            ->assertJsonPath('data.ya_en_examen', false)
            ->assertJsonPath('data.carrera', null);

        $this->assertSame('9876543', $response->json('data.ci'));
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

        $this->getJson($this->searchUrl($this->examId))
            ->assertOk()
            ->assertJsonPath('data.carrera.id_carrera', $this->sistemasId);
    }

    public function test_la_busqueda_avisa_si_ya_figura_en_la_lista_del_examen(): void
    {
        $examId = $this->createOwnExam(Exam::PROGRAMADO, [$this->grupoPropioId]);
        $this->enrollStudentWithSis($this->grupoPropioId, self::SIS);

        $this->getJson($this->searchUrl($examId))
            ->assertOk()
            ->assertJsonPath('data.ya_en_examen', true);
    }

    public function test_estar_en_otro_grupo_no_cuenta_como_estar_en_la_lista_del_examen(): void
    {
        $examId = $this->createOwnExam(Exam::PROGRAMADO, [$this->grupoPropioId]);
        $this->enrollStudentWithSis($this->segundoGrupoPropioId, self::SIS);

        $this->getJson($this->searchUrl($examId))
            ->assertOk()
            ->assertJsonPath('data.ya_en_examen', false);
    }

    public function test_un_codigo_que_no_existe_responde_404_con_el_mensaje_del_criterio(): void
    {
        $this->getJson($this->searchUrl($this->examId, '209999999'))
            ->assertNotFound()
            ->assertJsonPath('message', 'No se encontró a ningún estudiante con este código');
    }

    public function test_la_busqueda_exige_el_codigo(): void
    {
        $this->getJson("/api/docente/examenes/{$this->examId}/estudiantes-excepcionales/buscar")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['cod_sis']);
    }

    /* ------------------- registro: validaciones previas al guardado ------------------- */

    public function test_el_motivo_es_obligatorio(): void
    {
        $studentId = $this->createStudent();

        foreach ([null, '', '   '] as $motivo) {
            $this->register($studentId, ['motivo' => $motivo])
                ->assertStatus(422)
                ->assertJsonPath('errors.motivo.0', 'Indique el motivo del registro excepcional');
        }
    }

    public function test_el_motivo_necesita_un_minimo_de_caracteres(): void
    {
        $this->register($this->createStudent(), ['motivo' => 'abcd'])
            ->assertStatus(422)
            ->assertJsonPath('errors.motivo.0', 'El motivo debe tener al menos 5 caracteres.');
    }

    public function test_el_motivo_no_puede_pasar_de_255_caracteres(): void
    {
        $this->register($this->createStudent(), ['motivo' => str_repeat('a', 256)])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['motivo']);
    }

    public function test_un_estudiante_inexistente_se_rechaza(): void
    {
        DB::table('examen')->where('id_examen', $this->examId)->update(['ingreso_abierto_en' => now()]);
        $this->register(999999)
            ->assertStatus(422)
            ->assertJsonPath('errors.id_estudiante.0', 'No se encontró a ningún estudiante con este código');
    }

    public function test_un_estudiante_que_ya_figura_en_la_lista_del_examen_se_rechaza(): void
    {
        $examId = $this->createOwnExam(Exam::PROGRAMADO, [$this->grupoPropioId]);
        $this->enrollStudentWithSis($this->grupoPropioId, self::SIS);
        $studentId = (int) DB::table('estudiante')->where('cod_sis', self::SIS)->value('id_estudiante');
        DB::table('examen')->where('id_examen', $examId)->update(['ingreso_abierto_en' => now()]);

        $this->register($studentId, [], $examId)
            ->assertStatus(422)
            ->assertJsonPath('errors.id_estudiante.0', 'Ya figura en la lista de estudiantes de este examen');
    }

    public function test_un_examen_en_curso_sin_control_abierto_responde_409(): void
    {
        $examId = $this->createOwnExam(Exam::EN_CURSO, [$this->grupoPropioId]);

        $this->register($this->createStudent(), [], $examId)
            ->assertStatus(409)
            ->assertJsonPath('message', 'El examen ya no admite estudiantes excepcionales.');
    }

    /* --------------------------------- permisos --------------------------------- */

    public function test_otro_docente_no_puede_usar_un_examen_ajeno(): void
    {
        $studentId = $this->createStudent();
        $this->makeExamForeign($this->examId);

        $this->register($studentId)->assertForbidden();
        $this->getJson($this->searchUrl($this->examId))->assertForbidden();
    }

    public function test_un_examen_inexistente_responde_404(): void
    {
        $this->register($this->createStudent(), [], 999999)->assertNotFound();
        $this->getJson($this->searchUrl(999999))->assertNotFound();
    }

    public function test_un_id_de_examen_invalido_responde_404_sin_llegar_a_la_base(): void
    {
        $this->getJson('/api/docente/examenes/abc/estudiantes-excepcionales/buscar?cod_sis=1')->assertNotFound();
        $this->postJson('/api/docente/examenes/1234567890/estudiantes-excepcionales', [])->assertNotFound();
    }

    public function test_el_invitado_recibe_403(): void
    {
        $studentId = $this->createStudent();
        $this->actAsUserId($this->createInvitedTeacher());

        $this->getJson(self::EXAMS_URL)->assertForbidden()->assertJsonPath('message', self::FORBIDDEN_MESSAGE);
        $this->getJson($this->searchUrl($this->examId))->assertForbidden();
        $this->register($studentId)->assertForbidden()->assertJsonPath('message', self::FORBIDDEN_MESSAGE);
    }

    public function test_un_auxiliar_recibe_403(): void
    {
        $studentId = $this->createStudent();
        $this->actAsUserId($this->createAssistant('Beto', '202400010'));

        $this->getJson(self::EXAMS_URL)->assertForbidden();
        $this->register($studentId)->assertForbidden()->assertJsonPath('message', self::FORBIDDEN_MESSAGE);
    }

    public function test_sin_sesion_responde_401(): void
    {
        $this->actAsGuest();

        $this->getJson(self::EXAMS_URL)->assertUnauthorized();
        $this->getJson($this->searchUrl($this->examId))->assertUnauthorized();
        $this->postJson($this->storeUrl($this->examId), [])->assertUnauthorized();
    }

    /* ------------- pendientes: dependen de dónde se guarda el registro ------------- */

    public function test_registra_al_estudiante_en_la_lista_del_examen_con_su_motivo(): void
    {
        $studentId = $this->createStudent();
        $groupId = (int) DB::table('grupo_examen')
            ->where('id_examen', $this->examId)->value('id_grupo');
        $this->assertGreaterThan(0, $groupId);

        $careerId = (int) DB::table('grupo')->where('id_grupo', $groupId)->value('id_carrera');
        $this->assertGreaterThan(0, $careerId);
        if (! DB::table('grupo_oferta')->where('id_grupo', $groupId)
            ->where('id_carrera', $careerId)->exists()) {
            $group = DB::table('grupo')->where('id_grupo', $groupId)
                ->first(['id_materia', 'id_gestion_periodo']);
            $this->assertNotNull($group);
            $this->assertGreaterThan(0, (int) $group->id_materia);
            $this->assertGreaterThan(0, (int) $group->id_gestion_periodo);
            DB::table('grupo_oferta')->insert([
                'id_grupo' => $groupId,
                'id_carrera' => $careerId,
                'id_materia' => (int) $group->id_materia,
                'id_gestion_periodo' => (int) $group->id_gestion_periodo,
            ]);
        }

        $roomId = (int) DB::table('ambiente')->insertGetId([
            'nro_aula' => 'HU023-T',
            'capacidad' => 40,
            'ubicacion' => 'Pruebas HU-023',
            'estado' => RecordStatus::ACTIVE,
        ], 'id_ambiente');
        DB::table('examen_ambiente')->insert([
            'id_examen' => $this->examId,
            'id_ambiente' => $roomId,
        ]);
        DB::table('examen')->where('id_examen', $this->examId)
            ->update(['ingreso_abierto_en' => now()]);

        $this->register($studentId, [
            'id_grupo' => $groupId,
            'id_carrera' => $careerId,
            'id_ambiente' => $roomId,
        ])->assertSuccessful();

        $this->assertDatabaseHas('examen_estudiante_excepcional', [
            'id_examen' => $this->examId,
            'id_estudiante' => $studentId,
            'id_grupo' => $groupId,
            'id_carrera' => $careerId,
            'motivo' => self::REASON,
            'id_docente' => $this->docenteId,
        ]);
        $this->assertDatabaseHas('examen_estudiante', [
            'id_examen' => $this->examId,
            'id_estudiante' => $studentId,
            'id_grupo' => $groupId,
            'id_ambiente' => $roomId,
            'estado_habilitacion' => 'HABILITADO',
        ]);
        $this->assertDatabaseMissing('grupo_estudiante', [
            'id_grupo' => $groupId,
            'id_estudiante' => $studentId,
        ]);
    }

    public function test_el_registro_queda_en_la_bitacora_con_el_examen(): void
    {
        // Reutiliza el escenario de registro exitoso, con transaccion de prueba.
        $this->test_registra_al_estudiante_en_la_lista_del_examen_con_su_motivo();

        $studentId = (int) DB::table('examen_estudiante_excepcional')
            ->where('id_examen', $this->examId)
            ->value('id_estudiante');
        $this->assertGreaterThan(0, $studentId);

        $actionId = DB::table('accion')
            ->where('operacion', 'REGISTRAR_ESTUDIANTE_EXCEPCIONAL')
            ->value('id_accion');
        $this->assertNotNull($actionId, 'Falta la accion de auditoria en la base de pruebas.');

        $audit = DB::table('log')
            ->where('id_accion', $actionId)
            ->where('id_usuario', $this->docenteId)
            ->where('tabla_afectada', 'examen_estudiante_excepcional')
            ->orderByDesc('id_log')
            ->first();
        $this->assertNotNull($audit, 'No se encontro el registro en bitacora.');

        $after = json_decode((string) $audit->nuevo_valor, true);
        $this->assertIsArray($after);
        $this->assertSame((int) $this->examId, (int) ($after['id_examen'] ?? 0));
        $this->assertSame($studentId, (int) ($after['id_estudiante'] ?? 0));
        $this->assertSame($this->docenteId, $after['id_docente'] ?? null);
        $this->assertSame(self::REASON, $after['motivo'] ?? null);
        }

    public function test_los_otros_examenes_del_mismo_grupo_quedan_intactos(): void
    {
                $otherExamId = $this->createOwnExam(Exam::PROGRAMADO, [$this->grupoPropioId]);
        $this->assertNotEquals($this->examId, $otherExamId);

        $this->test_registra_al_estudiante_en_la_lista_del_examen_con_su_motivo();
        $studentId = (int) DB::table('estudiante')->where('cod_sis', self::SIS)->value('id_estudiante');
        $this->assertGreaterThan(0, $studentId);
        $this->assertDatabaseMissing('examen_estudiante_excepcional', [
            'id_examen' => $otherExamId,
            'id_estudiante' => $studentId,
        ]);
        $this->assertDatabaseMissing('examen_estudiante', [
            'id_examen' => $otherExamId,
            'id_estudiante' => $studentId,
        ]);
    }

    public function test_el_padron_del_grupo_queda_intacto(): void
    {
                $before = DB::table('grupo_estudiante')->count();
        $this->test_registra_al_estudiante_en_la_lista_del_examen_con_su_motivo();
        $this->assertSame($before, DB::table('grupo_estudiante')->count());
    }

    public function test_una_nueva_carga_de_nomina_no_afecta_al_excepcional(): void
    {
        // Usa el registro exitoso probado y conserva sus datos.
        $this->test_registra_al_estudiante_en_la_lista_del_examen_con_su_motivo();

        $exceptional = DB::table('examen_estudiante_excepcional')
            ->where('id_examen', $this->examId)->first();
        $this->assertNotNull($exceptional);
        $studentId = (int) $exceptional->id_estudiante;
        $groupId = (int) $exceptional->id_grupo;
        $before = DB::table('examen_estudiante')
            ->where('id_examen', $this->examId)
            ->where('id_estudiante', $studentId)->first();
        $this->assertNotNull($before);

        // La nomina esta bloqueada durante el ingreso del examen.
        $preview = $this->previewRoster($this->csvFile($this->rosterRows(2)), $groupId);
        $preview->assertStatus(422);

        $this->assertDatabaseHas('examen_estudiante_excepcional', [
            'id_examen' => $this->examId,
            'id_estudiante' => $studentId,
            'id_grupo' => $groupId,
            'id_carrera' => $exceptional->id_carrera,
            'motivo' => $exceptional->motivo,
            'id_docente' => $exceptional->id_docente,
        ]);
        $this->assertDatabaseHas('examen_estudiante', [
            'id_examen' => $this->examId,
            'id_estudiante' => $studentId,
            'id_grupo' => $groupId,
            'id_ambiente' => $before->id_ambiente,
            'estado_habilitacion' => 'HABILITADO',
        ]);
        $this->assertDatabaseMissing('grupo_estudiante', [
            'id_grupo' => $groupId,
            'id_estudiante' => $studentId,
        ]);
    }

    public function test_el_administrador_recibe_403(): void
    {
        $admin = User::create([
    'nombre' => 'Admin',
    'apellido_paterno' => 'Prueba',
    'correo' => 'admin.hu023@test.com',
    'contrasenia' => 'x',
    'cod_sis' => '202499998',
    'estado' => RecordStatus::ACTIVE,
]);

$role = Role::firstOrCreate(
    ['nombre_rol' => Role::ADMINISTRADOR],
    ['descripcion' => 'Administrador', 'estado' => RecordStatus::ACTIVE]
);

UserRole::create([
    'id_usuario' => $admin->id_usuario,
    'id_rol' => $role->id_rol,
    'fecha_inicio' => now(),
]);

$this->actAsUserId($admin->id_usuario);
$this->register(999999)->assertForbidden();
    }

    /* -------- HU-023: autorización de docentes colaboradores -------- */

    private function inviteOtherTeacher(string $state): void
    {
        DB::table('invitacion_examen')->insert([
            'id_examen' => $this->examId,
            'id_docente_creador' => $this->docenteId,
            'id_docente_invitado' => $this->otroDocenteId,
            'fecha_invitacion' => now()->subDay(),
            'fecha_respuesta' => $state === \App\Models\ExamInvitation::PENDIENTE ? null : now(),
            'estado' => $state,
        ]);
    }

    public function test_colaborador_aceptado_ve_examen_y_solo_sus_grupos_en_opciones(): void
    {
        DB::table('grupo_examen')->insert([
            'id_examen' => $this->examId,
            'id_grupo' => $this->grupoAjenoId,
        ]);
        $this->inviteOtherTeacher(\App\Models\ExamInvitation::ACEPTADA);
        $this->actAsUserId($this->otroDocenteId);

        $exams = $this->getJson(self::EXAMS_URL)->assertOk()->json('data');
        $this->assertContains($this->examId, array_column($exams, 'id_examen'));

        $options = $this->getJson("/api/docente/examenes/{$this->examId}/estudiantes-excepcionales/opciones")
            ->assertOk()->json('data');
        $ids = array_map('intval', array_column($options['grupos'], 'id_grupo'));
        $this->assertContains($this->grupoAjenoId, $ids);
        $this->assertNotContains($this->grupoPropioId, $ids);
    }

    public function test_colaborador_aceptado_no_puede_registrar_en_grupo_del_titular(): void
    {
        $studentId = $this->createStudent();
        DB::table('grupo_examen')->insert([
            'id_examen' => $this->examId,
            'id_grupo' => $this->grupoAjenoId,
        ]);
        $this->inviteOtherTeacher(\App\Models\ExamInvitation::ACEPTADA);
        DB::table('examen')->where('id_examen', $this->examId)
            ->update(['ingreso_abierto_en' => now()]);
        $this->actAsUserId($this->otroDocenteId);

        $this->register($studentId, ['id_grupo' => $this->grupoPropioId])
            ->assertForbidden();
        $this->assertDatabaseMissing('examen_estudiante_excepcional', [
            'id_examen' => $this->examId,
            'id_estudiante' => $studentId,
        ]);
    }

    public function test_invitacion_pendiente_no_concede_acceso_al_examen(): void
    {
        $this->inviteOtherTeacher(\App\Models\ExamInvitation::PENDIENTE);
        $this->actAsUserId($this->otroDocenteId);

        $exams = $this->getJson(self::EXAMS_URL)->assertOk()->json('data');
        $this->assertNotContains($this->examId, array_column($exams, 'id_examen'));
        $this->getJson("/api/docente/examenes/{$this->examId}/estudiantes-excepcionales/opciones")
            ->assertForbidden();
        $this->getJson($this->searchUrl($this->examId))->assertForbidden();
    }
}
