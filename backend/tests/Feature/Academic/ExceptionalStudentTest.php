<?php

namespace Tests\Feature\Academic;

use App\Models\Exam;
use App\Models\ExamInvitation;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use App\Services\EntryControl\EntryControlSnapshotService;
use App\Services\Exams\ExamHistoryService;
use App\Services\Exams\ExamInProgressQueryService;
use App\Services\Exams\ExamLifecycleService;
use App\Services\Exams\ExamParticipantService;
use App\Services\Exams\FinalReportService;
use App\Support\RecordStatus;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\Concerns\SeedsAssistantManagement;
use Tests\TestCase;

/**
 * HU-023: registrar un estudiante excepcional en UN examen propio.
 *
 * Solo el docente responsable del examen (id_usuario_docente), solo mientras está EN_CURSO. El estudiante
 * queda en la lista de ese examen (examen_estudiante, marca es_excepcional) y en ningún otro sitio: no entra al
 * padrón del grupo ni a la nómina. Desde ahí se comporta como cualquier estudiante del examen.
 */
class ExceptionalStudentTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsAssistantManagement;

    private const SIS = '202600777';

    private const EXAMS_URL = '/api/docente/estudiantes-excepcionales/examenes';

    private const FORBIDDEN_MESSAGE = 'Solo un docente puede registrar estudiantes excepcionales.';

    private const NOT_IN_PROGRESS_MESSAGE =
        'El examen ya no está en curso: no se pueden registrar estudiantes excepcionales.';

    private const ALREADY_LISTED_MESSAGE = 'Ya figura en la lista de estudiantes de este examen';

    private const REASON = 'Convalidación aprobada por la Dirección de Carrera';

    private int $roomId;

    private int $secondRoomId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAssistantManagement();
    }

    /* ------------------------------- ayudantes ------------------------------- */

    private function optionsUrl(int $examId): string
    {
        return "/api/docente/examenes/{$examId}/estudiantes-excepcionales/opciones";
    }

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

    private function createRoom(string $name, int $capacity, int $examId): int
    {
        $roomId = (int) DB::table('ambiente')->insertGetId([
            'nro_aula' => $name,
            'capacidad' => $capacity,
            'ubicacion' => 'Pruebas HU-023',
            'estado' => RecordStatus::ACTIVE,
        ], 'id_ambiente');

        DB::table('examen_ambiente')->insert(['id_examen' => $examId, 'id_ambiente' => $roomId]);

        return $roomId;
    }

    /** El grupo ofrece al menos su carrera: el registro exige una pareja grupo-carrera de la oferta. */
    private function ensureOffer(int $groupId): int
    {
        $existing = DB::table('grupo_oferta')->where('id_grupo', $groupId)->value('id_carrera');

        if ($existing !== null) {
            return (int) $existing;
        }

        $group = DB::table('grupo')->where('id_grupo', $groupId)->first();
        DB::table('grupo_oferta')->insert([
            'id_grupo' => $groupId,
            'id_carrera' => $group->id_carrera,
            'id_materia' => $group->id_materia,
            'id_gestion_periodo' => $group->id_gestion_periodo,
        ]);

        return (int) $group->id_carrera;
    }

    /**
     * Examen del docente que actúa, EN_CURSO, con la nómina de $students estudiantes y dos aulas.
     * Es el único estado en que se admiten excepcionales.
     */
    private function runningExam(int $students = 4, int $firstCapacity = 2, int $secondCapacity = 10): int
    {
        for ($i = 1; $i <= $students; $i++) {
            $this->enrollStudentWithSis($this->grupoPropioId, sprintf('2026001%02d', $i));
        }

        $examId = $this->createExamLinkedTo([$this->grupoPropioId], Exam::EN_CURSO);
        $this->roomId = $this->createRoom('B1', $firstCapacity, $examId);
        $this->secondRoomId = $this->createRoom('B2', $secondCapacity, $examId);
        $this->ensureOffer($this->grupoPropioId);

        $started = now('UTC')->subMinutes(20)->format('Y-m-d H:i:sP');
        DB::table('examen')->where('id_examen', $examId)->update([
            'fecha' => now()->toDateString(),
            'hora_inicio' => now()->subMinutes(20)->format('H:i:s'),
            'ingreso_abierto_en' => $started,
            'inicio_real_en' => $started,
            'duracion' => 120,
        ]);

        return $examId;
    }

    private function register(int $studentId, int $examId, array $overrides = [])
    {
        $groupId = (int) DB::table('grupo_examen')->where('id_examen', $examId)->value('id_grupo');
        $careerId = (int) DB::table('grupo_oferta')->where('id_grupo', $groupId)->value('id_carrera');
        $roomId = (int) DB::table('examen_ambiente')->where('id_examen', $examId)->orderBy('id_ambiente')
            ->value('id_ambiente');

        return $this->postJson($this->storeUrl($examId), array_merge([
            'id_estudiante' => $studentId,
            'id_grupo' => $groupId,
            'id_carrera' => $careerId,
            'id_ambiente' => $roomId,
            'motivo' => self::REASON,
        ], $overrides));
    }

    private function exceptionalRows(int $examId)
    {
        return DB::table('examen_estudiante')->where('id_examen', $examId)->where('es_excepcional', true);
    }

    private function createTeacherWithRole(string $role, string $sis, string $mail): string
    {
        $user = User::create([
            'nombre' => 'Rol',
            'apellido_paterno' => $role,
            'correo' => $mail,
            'contrasenia' => 'x',
            'cod_sis' => $sis,
            'estado' => RecordStatus::ACTIVE,
        ]);

        $roleRow = Role::firstOrCreate(
            ['nombre_rol' => $role],
            ['descripcion' => $role, 'estado' => RecordStatus::ACTIVE]
        );

        UserRole::create([
            'id_usuario' => $user->id_usuario,
            'id_rol' => $roleRow->id_rol,
            'fecha_inicio' => now(),
        ]);

        return $user->id_usuario;
    }

    private function inviteOtherTeacher(int $examId, string $state): void
    {
        DB::table('invitacion_examen')->insert([
            'id_examen' => $examId,
            'id_docente_creador' => $this->docenteId,
            'id_docente_invitado' => $this->otroDocenteId,
            'fecha_invitacion' => now()->subDay(),
            'fecha_respuesta' => $state === ExamInvitation::PENDIENTE ? null : now(),
            'estado' => $state,
        ]);
    }

    /** Filas de la nómina de un examen tal como las ve la fuente única, con la marca incluida. */
    private function expectedBySis(int $examId): array
    {
        return app(ExamParticipantService::class)->expected($examId)->get()
            ->keyBy('cod_sis')
            ->all();
    }

    /* --------------------------- exámenes del docente --------------------------- */

    public function test_solo_ofrece_los_examenes_en_curso_del_docente_responsable(): void
    {
        $running = $this->runningExam();
        // El base del escenario es PROGRAMADO y de este docente: aunque abra el ingreso, no se ofrece.
        DB::table('examen')->where('id_examen', $this->examId)->update(['ingreso_abierto_en' => now()]);
        $foreign = $this->createExamLinkedTo([$this->grupoAjenoId], Exam::EN_CURSO, $this->otroDocenteId);
        $collaborating = $this->createExamLinkedTo([$this->grupoAjenoId], Exam::EN_CURSO, $this->otroDocenteId);
        DB::table('invitacion_examen')->insert([
            'id_examen' => $collaborating,
            'id_docente_creador' => $this->otroDocenteId,
            'id_docente_invitado' => $this->docenteId,
            'fecha_invitacion' => now()->subDay(),
            'fecha_respuesta' => now(),
            'estado' => ExamInvitation::ACEPTADA,
        ]);

        $data = $this->getJson(self::EXAMS_URL)->assertOk()->json('data');

        $this->assertSame([$running], array_column($data, 'id_examen'));
        $this->assertNotContains($foreign, array_column($data, 'id_examen'));
        $this->assertNotContains($collaborating, array_column($data, 'id_examen'));
        $this->assertArrayNotHasKey('permitido', $data[0]);
    }

    public function test_sin_examenes_en_curso_la_lista_viene_vacia(): void
    {
        $this->getJson(self::EXAMS_URL)->assertOk()->assertJsonPath('data', []);
    }

    /* ------------------------------ opciones y búsqueda ------------------------------ */

    public function test_las_opciones_traen_los_grupos_carreras_y_solo_las_aulas_del_examen(): void
    {
        $examId = $this->runningExam();
        $otherExamRoom = $this->createRoom('OTRO', 30, $this->examId);

        $options = $this->getJson($this->optionsUrl($examId))->assertOk()->json('data');

        $this->assertSame([$this->grupoPropioId], array_column($options['grupos'], 'id_grupo'));
        $this->assertContains($this->sistemasId, array_column($options['carreras'], 'id_carrera'));
        $this->assertEqualsCanonicalizing(
            [$this->roomId, $this->secondRoomId],
            array_column($options['ambientes'], 'id_ambiente')
        );
        $this->assertNotContains($otherExamRoom, array_column($options['ambientes'], 'id_ambiente'));
    }

    public function test_busca_al_estudiante_por_codigo_sis_sin_exponer_el_ci(): void
    {
        $examId = $this->runningExam();
        $studentId = $this->createStudent();

        $response = $this->getJson($this->searchUrl($examId))
            ->assertOk()
            ->assertJsonPath('data.id_estudiante', $studentId)
            ->assertJsonPath('data.cod_sis', self::SIS)
            ->assertJsonPath('data.nombre_completo', 'Ana Quispe Mamani')
            ->assertJsonPath('data.ya_en_examen', false);

        $this->assertArrayNotHasKey('ci', $response->json('data'));
    }

    public function test_la_busqueda_avisa_si_ya_figura_en_la_lista_del_examen(): void
    {
        $examId = $this->runningExam();

        $this->getJson($this->searchUrl($examId, '202600101'))
            ->assertOk()
            ->assertJsonPath('data.ya_en_examen', true);
    }

    public function test_un_codigo_que_no_existe_responde_404_con_el_mensaje_del_criterio(): void
    {
        $examId = $this->runningExam();

        $this->getJson($this->searchUrl($examId, '209999999'))
            ->assertNotFound()
            ->assertJsonPath('message', 'No se encontró a ningún estudiante con este código');
    }

    public function test_el_codigo_sis_debe_tener_entre_8_y_9_digitos(): void
    {
        $examId = $this->runningExam();

        foreach (['1234567', '1234567890', 'ABC12345', '2026 0077', ''] as $sis) {
            $this->getJson($this->searchUrl($examId, $sis))
                ->assertStatus(422)
                ->assertJsonValidationErrors(['cod_sis']);
        }

        $this->getJson($this->searchUrl($examId, '12345678'))->assertNotFound();
    }

    /* ------------------------- registro: validaciones previas ------------------------- */

    public function test_el_motivo_es_obligatorio_con_un_minimo_y_un_maximo(): void
    {
        $examId = $this->runningExam();
        $studentId = $this->createStudent();

        foreach ([null, '', '   '] as $reason) {
            $this->register($studentId, $examId, ['motivo' => $reason])
                ->assertStatus(422)
                ->assertJsonPath('errors.motivo.0', 'Indique el motivo del registro excepcional');
        }

        $this->register($studentId, $examId, ['motivo' => 'abcd'])
            ->assertStatus(422)
            ->assertJsonPath('errors.motivo.0', 'El motivo debe tener al menos 5 caracteres.');
        $this->register($studentId, $examId, ['motivo' => str_repeat('a', 256)])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['motivo']);

        $this->assertSame(0, $this->exceptionalRows($examId)->count());
    }

    public function test_un_estudiante_inexistente_se_rechaza(): void
    {
        $this->register(999999, $this->runningExam())
            ->assertStatus(422)
            ->assertJsonPath('errors.id_estudiante.0', 'No se encontró a ningún estudiante con este código');
    }

    public function test_el_grupo_la_carrera_y_el_aula_deben_corresponder_al_examen(): void
    {
        $examId = $this->runningExam();
        $studentId = $this->createStudent();
        $foreignRoom = $this->createRoom('AJENA', 30, $this->examId);

        $this->register($studentId, $examId, ['id_grupo' => $this->grupoAjenoId])
            ->assertStatus(422)->assertJsonValidationErrors(['id_grupo']);
        $this->register($studentId, $examId, ['id_carrera' => $this->sistemasId + 9999])
            ->assertStatus(422)->assertJsonValidationErrors(['id_carrera']);
        $this->register($studentId, $examId, ['id_ambiente' => $foreignRoom])
            ->assertStatus(422)->assertJsonValidationErrors(['id_ambiente']);

        $this->assertSame(0, $this->exceptionalRows($examId)->count());
    }

    /* ------------------------ estado del examen (solo EN_CURSO) ------------------------ */

    public function test_un_examen_programado_responde_409_aunque_tenga_el_ingreso_abierto(): void
    {
        $examId = $this->runningExam();
        $studentId = $this->createStudent();
        DB::table('examen')->where('id_examen', $examId)->update(['estado' => Exam::PROGRAMADO]);

        $this->register($studentId, $examId)
            ->assertStatus(409)
            ->assertJsonPath('message', self::NOT_IN_PROGRESS_MESSAGE);

        $this->assertSame(0, $this->exceptionalRows($examId)->count());
    }

    public function test_un_examen_finalizado_responde_409(): void
    {
        $examId = $this->runningExam();
        $studentId = $this->createStudent();
        DB::table('examen')->where('id_examen', $examId)->update(['estado' => Exam::FINALIZADO]);

        $this->register($studentId, $examId)
            ->assertStatus(409)
            ->assertJsonPath('message', self::NOT_IN_PROGRESS_MESSAGE);
    }

    /* ----------------------------------- permisos ----------------------------------- */

    public function test_otro_docente_no_puede_usar_un_examen_ajeno(): void
    {
        $examId = $this->runningExam();
        $studentId = $this->createStudent();
        DB::table('examen')->where('id_examen', $examId)->update(['id_usuario_docente' => $this->otroDocenteId]);

        $this->register($studentId, $examId)->assertForbidden();
        $this->getJson($this->searchUrl($examId))->assertForbidden();
        $this->getJson($this->optionsUrl($examId))->assertForbidden();
        $this->assertSame(0, $this->exceptionalRows($examId)->count());
    }

    public function test_un_colaborador_aceptado_recibe_403_en_todo(): void
    {
        $examId = $this->runningExam();
        $studentId = $this->createStudent();
        $this->inviteOtherTeacher($examId, ExamInvitation::ACEPTADA);
        $this->actAsUserId($this->otroDocenteId);

        $offered = $this->getJson(self::EXAMS_URL)->assertOk()->json('data');
        $this->assertNotContains($examId, array_column($offered, 'id_examen'));
        $this->getJson($this->optionsUrl($examId))->assertForbidden();
        $this->getJson($this->searchUrl($examId))->assertForbidden();
        $this->register($studentId, $examId)->assertForbidden();

        $this->assertSame(0, $this->exceptionalRows($examId)->count());
    }

    public function test_una_invitacion_pendiente_no_concede_acceso(): void
    {
        $examId = $this->runningExam();
        $this->inviteOtherTeacher($examId, ExamInvitation::PENDIENTE);
        $this->actAsUserId($this->otroDocenteId);

        $this->getJson($this->optionsUrl($examId))->assertForbidden();
        $this->getJson($this->searchUrl($examId))->assertForbidden();
    }

    public function test_el_invitado_recibe_403(): void
    {
        $examId = $this->runningExam();
        $studentId = $this->createStudent();
        $this->actAsUserId($this->createTeacherWithRole(Role::INVITADO, '202400099', 'invitada@test.com'));

        $this->getJson(self::EXAMS_URL)->assertForbidden()->assertJsonPath('message', self::FORBIDDEN_MESSAGE);
        $this->getJson($this->optionsUrl($examId))->assertForbidden();
        $this->getJson($this->searchUrl($examId))->assertForbidden();
        $this->register($studentId, $examId)->assertForbidden()->assertJsonPath('message', self::FORBIDDEN_MESSAGE);
    }

    public function test_un_auxiliar_recibe_403(): void
    {
        $examId = $this->runningExam();
        $studentId = $this->createStudent();
        $this->actAsUserId($this->createAssistant('Beto', '202400010'));

        $this->getJson(self::EXAMS_URL)->assertForbidden();
        $this->getJson($this->optionsUrl($examId))->assertForbidden();
        $this->getJson($this->searchUrl($examId))->assertForbidden();
        $this->register($studentId, $examId)->assertForbidden()->assertJsonPath('message', self::FORBIDDEN_MESSAGE);
    }

    public function test_el_administrador_recibe_403(): void
    {
        $examId = $this->runningExam();
        $studentId = $this->createStudent();
        $this->actAsUserId($this->createTeacherWithRole(Role::ADMINISTRADOR, '202499998', 'admin.hu023@test.com'));

        $this->getJson(self::EXAMS_URL)->assertForbidden();
        $this->getJson($this->optionsUrl($examId))->assertForbidden();
        $this->getJson($this->searchUrl($examId))->assertForbidden();
        $this->register($studentId, $examId)->assertForbidden();
    }

    public function test_una_cuenta_inactiva_recibe_403(): void
    {
        $examId = $this->runningExam();
        $studentId = $this->createStudent();
        DB::table('usuario')->where('id_usuario', $this->docenteId)->update(['estado' => RecordStatus::INACTIVE]);

        $this->register($studentId, $examId)->assertForbidden();
    }

    public function test_un_examen_inexistente_o_un_id_invalido_responde_404(): void
    {
        // Ids positivos para que la validación no responda 422 antes de comprobar que el examen existe.
        $this->register($this->createStudent(), 999999, ['id_grupo' => 1, 'id_carrera' => 1, 'id_ambiente' => 1])
            ->assertNotFound();
        $this->getJson($this->searchUrl(999999))->assertNotFound();
        $this->getJson('/api/docente/examenes/abc/estudiantes-excepcionales/buscar?cod_sis=1')->assertNotFound();
        $this->postJson('/api/docente/examenes/1234567890/estudiantes-excepcionales', [])->assertNotFound();
    }

    public function test_sin_sesion_responde_401(): void
    {
        $examId = $this->runningExam();
        $this->actAsGuest();

        $this->getJson(self::EXAMS_URL)->assertUnauthorized();
        $this->getJson($this->searchUrl($examId))->assertUnauthorized();
        $this->postJson($this->storeUrl($examId), [])->assertUnauthorized();
    }

    /* ------------------------------ registro correcto ------------------------------ */

    public function test_registra_al_estudiante_solo_en_la_lista_de_ese_examen(): void
    {
        $examId = $this->runningExam();
        $studentId = $this->createStudent();
        $otherExam = $this->createExamLinkedTo([$this->grupoPropioId], Exam::EN_CURSO);
        $rosterBefore = DB::table('grupo_estudiante')->count();

        $response = $this->register($studentId, $examId, ['id_ambiente' => $this->secondRoomId])
            ->assertCreated()
            ->assertJsonPath('data.cod_sis', self::SIS)
            ->assertJsonPath('data.motivo_excepcional', self::REASON);

        $this->assertArrayNotHasKey('ci', $response->json('data'));
        $this->assertDatabaseHas('examen_estudiante', [
            'id_examen' => $examId,
            'id_estudiante' => $studentId,
            'es_excepcional' => true,
            'id_grupo' => null,
            'id_grupo_excepcional' => $this->grupoPropioId,
            'id_grupo_efectivo' => $this->grupoPropioId,
            'id_carrera_excepcional' => $this->sistemasId,
            'id_usuario_docente_excepcional' => $this->docenteId,
            'motivo_excepcional' => self::REASON,
            'id_ambiente' => $this->secondRoomId,
            'estado_ingreso' => 'NO_INGRESO',
            'estado_habilitacion' => 'HABILITADO',
        ]);
        $this->assertSame($rosterBefore, DB::table('grupo_estudiante')->count());
        $this->assertDatabaseMissing('grupo_estudiante', ['id_estudiante' => $studentId]);
        $this->assertDatabaseMissing('examen_estudiante', ['id_examen' => $otherExam, 'id_estudiante' => $studentId]);
        $this->assertArrayNotHasKey(self::SIS, $this->expectedBySis($otherExam));
    }

    public function test_el_registro_queda_en_la_bitacora(): void
    {
        $examId = $this->runningExam();
        $studentId = $this->createStudent();

        $this->register($studentId, $examId)->assertCreated();

        $audit = DB::table('log')
            ->join('accion', 'accion.id_accion', '=', 'log.id_accion')
            ->where('accion.operacion', 'REGISTRAR_ESTUDIANTE_EXCEPCIONAL')
            ->where('log.id_usuario', $this->docenteId)
            ->orderByDesc('log.id_log')
            ->first(['log.tabla_afectada', 'log.nuevo_valor']);

        $this->assertNotNull($audit);
        $this->assertSame('examen_estudiante', $audit->tabla_afectada);
        $after = json_decode((string) $audit->nuevo_valor, true);
        $this->assertSame($examId, $after['id_examen']);
        $this->assertSame($studentId, $after['id_estudiante']);
        $this->assertSame(self::REASON, $after['motivo']);
    }

    public function test_dos_veces_el_mismo_excepcional_deja_una_sola_fila_y_el_mensaje_del_criterio(): void
    {
        $examId = $this->runningExam();
        $studentId = $this->createStudent();

        $this->register($studentId, $examId)->assertCreated();
        $this->register($studentId, $examId)
            ->assertStatus(422)
            ->assertJsonPath('errors.id_estudiante.0', self::ALREADY_LISTED_MESSAGE);

        $this->assertSame(1, DB::table('examen_estudiante')
            ->where('id_examen', $examId)->where('id_estudiante', $studentId)->count());
    }

    public function test_un_estudiante_que_ya_esta_en_la_nomina_recibe_el_mensaje_del_criterio_y_no_se_duplica(): void
    {
        $examId = $this->runningExam();
        $rosterStudent = (int) DB::table('estudiante')->where('cod_sis', '202600101')->value('id_estudiante');

        $this->register($rosterStudent, $examId)
            ->assertStatus(422)
            ->assertJsonPath('errors.id_estudiante.0', self::ALREADY_LISTED_MESSAGE);

        $this->assertSame(0, $this->exceptionalRows($examId)->count());
    }

    public function test_la_base_no_admite_dos_filas_del_mismo_estudiante_en_un_examen(): void
    {
        $examId = $this->runningExam();
        $studentId = $this->createStudent();
        $this->register($studentId, $examId)->assertCreated();

        $this->expectException(QueryException::class);
        DB::table('examen_estudiante')->insert([
            'id_examen' => $examId,
            'id_estudiante' => $studentId,
            'id_grupo' => $this->grupoPropioId,
            'estado_habilitacion' => 'HABILITADO',
            'estado_ingreso' => 'NO_INGRESO',
        ]);
    }

    /* ----------------------- se comporta como cualquier estudiante ----------------------- */

    public function test_la_fuente_unica_de_esperados_lo_incluye_con_su_marca_y_los_conteos_lo_cuentan(): void
    {
        $examId = $this->runningExam();
        $studentId = $this->createStudent();
        $before = app(ExamParticipantService::class)->counts($examId);

        $this->register($studentId, $examId)->assertCreated();

        $expected = $this->expectedBySis($examId);
        $this->assertTrue((bool) $expected[self::SIS]->es_excepcional);
        $this->assertSame(self::REASON, $expected[self::SIS]->motivo_excepcional);
        $this->assertSame($this->grupoPropioId, (int) $expected[self::SIS]->id_grupo);
        $this->assertFalse((bool) $expected['202600101']->es_excepcional);

        $after = app(ExamParticipantService::class)->counts($examId);
        $this->assertSame($before['esperados'] + 1, $after['esperados']);
        $this->assertSame($before['pendientes'] + 1, $after['pendientes']);
        $this->assertSame($before['ingresados'], $after['ingresados']);
    }

    public function test_el_listado_de_control_de_ingreso_cuenta_al_excepcional_en_el_total(): void
    {
        $examId = $this->runningExam();
        $studentId = $this->createStudent();
        $this->register($studentId, $examId)->assertCreated();

        $exam = collect(app(\App\Services\EntryControl\EntryAccessService::class)
            ->openExamsFor(User::findOrFail($this->docenteId)))->firstWhere('id_examen', $examId);

        $this->assertNotNull($exam);
        $this->assertSame(5, $exam['total']);
        $this->assertSame(0, $exam['ingresados']);
        $this->assertSame(5, $exam['pendientes']);
    }

    public function test_el_examen_en_curso_lo_lista_con_su_marca_y_motivo(): void
    {
        $examId = $this->runningExam();
        $studentId = $this->createStudent();
        $this->register($studentId, $examId)->assertCreated();

        $students = collect(app(ExamInProgressQueryService::class)
            ->show(Exam::findOrFail($examId), User::findOrFail($this->docenteId))['estudiantes'])
            ->keyBy('cod_sis');

        $this->assertTrue($students[self::SIS]['es_excepcional']);
        $this->assertSame(self::REASON, $students[self::SIS]['motivo_excepcional']);
        $this->assertFalse($students['202600101']['es_excepcional']);
    }

    public function test_registrarlo_no_cambia_el_aula_de_ningun_otro_estudiante(): void
    {
        // B1 (capacidad 2) y B2: el reparto automático llena B1 con los dos primeros de la nómina.
        $examId = $this->runningExam(4, 2, 10);
        $snapshots = app(EntryControlSnapshotService::class);
        $exam = Exam::findOrFail($examId);
        $roomsOf = function () use ($snapshots, $exam): array {
            return DB::table('estudiante')->where('cod_sis', 'like', '2026001%')->orderBy('cod_sis')
                ->pluck('id_estudiante', 'cod_sis')
                ->map(fn ($id) => $snapshots->recordFor($exam, (int) $id)['ambiente_asignado']['nro_aula'] ?? null)
                ->all();
        };
        $inProgress = fn (): array => collect(app(ExamInProgressQueryService::class)
            ->show(Exam::findOrFail($examId), User::findOrFail($this->docenteId))['estudiantes'])
            ->where('es_excepcional', false)->pluck('ambiente', 'cod_sis')->all();

        $roomsBefore = $roomsOf();
        $inProgressBefore = $inProgress();
        // El excepcional se registra en B1, que ya está llena: no debe desplazar a nadie.
        $this->register($this->createStudent(), $examId, ['id_ambiente' => $this->roomId])->assertCreated();

        $this->assertSame($roomsBefore, $roomsOf());
        $this->assertSame($inProgressBefore, $inProgress());
        $exceptionalId = (int) DB::table('estudiante')->where('cod_sis', self::SIS)->value('id_estudiante');
        $record = $snapshots->recordFor($exam, $exceptionalId);
        $this->assertSame('B1', $record['ambiente_asignado']['nro_aula']);
    }

    public function test_el_control_de_ingreso_lo_encuentra_por_sis_y_ci_y_muestra_la_marca(): void
    {
        $examId = $this->runningExam();
        $studentId = $this->createStudent();
        $snapshots = app(EntryControlSnapshotService::class);
        $this->register($studentId, $examId)->assertCreated();

        $token = $snapshots->stage(Exam::findOrFail($examId));
        $snapshots->activate($examId, $token);

        $bySis = $snapshots->studentBySis($examId, self::SIS);
        $this->assertNotNull($bySis);
        $this->assertSame($studentId, $bySis['estudiante']['id_estudiante']);
        $this->assertTrue($bySis['es_excepcional']);
        $this->assertSame(self::REASON, $bySis['motivo_excepcional']);
        $this->assertSame($studentId, $snapshots->studentByCi($examId, '9876543')['estudiante']['id_estudiante']);
        $this->assertFalse($snapshots->studentBySis($examId, '202600101')['es_excepcional']);

        $snapshots->deactivate($examId);
    }

    public function test_registrarlo_con_el_control_abierto_lo_suma_al_estado_activo_sin_reconstruirlo(): void
    {
        $examId = $this->runningExam();
        $studentId = $this->createStudent();
        $snapshots = app(EntryControlSnapshotService::class);
        $docente = User::findOrFail($this->docenteId);

        $token = $snapshots->stage(Exam::findOrFail($examId));
        $snapshots->activate($examId, $token);
        $before = $snapshots->currentStatus($examId, $docente, null);
        $this->assertNull($snapshots->studentBySis($examId, self::SIS));

        $this->register($studentId, $examId)->assertCreated();

        $found = $snapshots->studentBySis($examId, self::SIS);
        $this->assertNotNull($found);
        $this->assertTrue($found['es_excepcional']);
        $status = $snapshots->currentStatus($examId, $docente, null);
        $this->assertSame($before['total'] + 1, $status['total']);
        $this->assertSame($before['pendientes'] + 1, $status['pendientes']);
        $suggested = $snapshots->search($examId, $docente, 'Ana Quispe');
        $this->assertContains($studentId, array_column($suggested, 'id_estudiante'));
        // El estado activo es el mismo: no se rearmó con un token nuevo.
        $this->assertTrue($snapshots->ready($examId));

        $snapshots->deactivate($examId);
    }

    public function test_si_confirma_su_ingreso_se_cuenta_como_ingresado_sin_romper_la_base(): void
    {
        $examId = $this->runningExam();
        $studentId = $this->createStudent();
        $this->register($studentId, $examId)->assertCreated();

        // El ingreso actualiza la fila existente: no debe reescribir id_grupo (el CHECK lo prohíbe).
        DB::table('examen_estudiante')
            ->where('id_examen', $examId)->where('id_estudiante', $studentId)
            ->update([
                'estado_ingreso' => 'INGRESO',
                'hora_ingreso' => '08:05:00',
                'id_usuario_controlador' => $this->docenteId,
            ]);

        $counts = app(ExamParticipantService::class)->counts($examId);
        $this->assertSame(1, $counts['ingresados']);
        $this->assertSame(5, $counts['esperados']);
        $this->assertSame(4, $counts['pendientes']);
    }

    /* --------------------- examen finalizado: ausente, reporte e historial --------------------- */

    public function test_si_nunca_ingresa_queda_como_ausente_en_conteos_historial_y_reporte(): void
    {
        $examId = $this->runningExam();
        $studentId = $this->createStudent();
        $this->register($studentId, $examId)->assertCreated();

        app(ExamLifecycleService::class)->finishManually(Exam::findOrFail($examId), $this->docenteId);

        $counts = app(ExamParticipantService::class)->counts($examId);
        $this->assertSame(5, $counts['esperados']);

        $attendance = new ReflectionMethod(ExamHistoryService::class, 'attendanceFor');
        $attendance->setAccessible(true);
        $row = $attendance->invoke(app(ExamHistoryService::class), [$examId])[$examId];
        $this->assertSame(5, $row['esperados']);
        $this->assertSame(5, $row['ausentes']);

        $report = app(FinalReportService::class)->build(Exam::findOrFail($examId), $this->docenteId);
        $this->assertSame(5, $report['asistencia']['totales']['inscritos']);
        $this->assertSame(5, $report['asistencia']['totales']['ausentes']);
        $student = collect($report['estudiantes'])->firstWhere('cod_sis', self::SIS);
        $this->assertSame('Ausente', $student['estado']);
        $this->assertTrue($student['es_excepcional']);
        $this->assertSame(self::REASON, $student['motivo_excepcional']);
    }

    public function test_la_cantidad_congelada_por_grupo_incluye_al_excepcional(): void
    {
        $examId = $this->runningExam();
        $this->register($this->createStudent(), $examId)->assertCreated();

        $finished = app(ExamLifecycleService::class)->finishManually(Exam::findOrFail($examId), $this->docenteId);

        $group = $finished->groups->firstWhere('id_grupo', $this->grupoPropioId);
        $this->assertSame(5, (int) $group->cantidad_estudiantes);
    }

    public function test_los_examenes_sin_excepcionales_dan_los_mismos_numeros_que_antes(): void
    {
        $examId = $this->runningExam();
        DB::table('examen_estudiante')->insert([
            'id_examen' => $examId,
            'id_estudiante' => (int) DB::table('estudiante')->where('cod_sis', '202600101')->value('id_estudiante'),
            'id_grupo' => $this->grupoPropioId,
            'estado_habilitacion' => 'HABILITADO',
            'estado_ingreso' => 'INGRESO',
            'hora_ingreso' => '08:00:00',
        ]);

        $this->assertSame(
            ['esperados' => 4, 'ingresados' => 1, 'pendientes' => 3],
            app(ExamParticipantService::class)->counts($examId)
        );

        app(ExamLifecycleService::class)->finishManually(Exam::findOrFail($examId), $this->docenteId);

        $this->assertSame(
            ['esperados' => 4, 'ingresados' => 1, 'pendientes' => 3],
            app(ExamParticipantService::class)->counts($examId)
        );
        $report = app(FinalReportService::class)->build(Exam::findOrFail($examId), $this->docenteId);
        $this->assertSame(3, $report['asistencia']['totales']['ausentes']);
        $this->assertSame(0, collect($report['estudiantes'])->where('es_excepcional', true)->count());
    }
}
