<?php

namespace Tests\Feature\Exams;

use App\Models\AdmissionCall;
use App\Models\Exam;
use App\Models\ExamAssistant;
use App\Models\Group;
use App\Models\Role;
use App\Models\StudentExam;
use App\Services\EntryControl\EntryControlSnapshotService;
use App\Services\EntryControl\RoomAssignmentService;
use App\Support\RecordStatus;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\Fluent\AssertableJson;
use LogicException;
use Mockery\MockInterface;
use Predis\Connection\ConnectionException;
use Predis\Connection\NodeConnectionInterface;
use Tests\Concerns\AssignsIncidentRole;
use Tests\Concerns\SeedsExamAssistants;
use Tests\TestCase;

class ExamAttendanceIndexTest extends TestCase
{
    use AssignsIncidentRole;
    use DatabaseTransactions;
    use SeedsExamAssistants;

    private const URL = '/api/examenes/estudiantes-ingresados';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedExamAssistants();
    }

    public function test_devuelve_el_contrato_del_catalogo_para_un_examen_en_curso_sin_leer_el_snapshot(): void
    {
        $exam = $this->createExam(['estado' => Exam::EN_CURSO], [$this->aulaId]);
        $exam->groups()->attach([$this->grupoPeriodoAnteriorId, $this->grupoPropioId]);
        $groups = Group::query()->whereIn('id_grupo', [$this->grupoPropioId, $this->grupoPeriodoAnteriorId])
            ->orderBy('num_grupo')->orderBy('id_grupo')->get();
        $this->expectNoSnapshotRead();

        $this->getJson(self::URL)->assertOk()->assertExactJson(['data' => [[
            'id_examen' => $exam->id_examen,
            'nombre_examen' => $exam->nombre_examen,
            'materia' => ['id_materia' => $this->calculoId, 'nombre' => 'Calculo II', 'codigo' => 'MAT-102'],
            'grupos' => $groups->map(static fn (Group $group): array => [
                'id_grupo' => $group->id_grupo,
                'num_grupo' => $group->num_grupo,
            ])->all(),
            'fecha' => $exam->fecha->toDateString(),
            'hora_inicio' => '08:00',
            'hora_fin' => '09:30',
            'estado' => Exam::EN_CURSO,
            'ambiente' => null,
        ]]]);
    }

    public function test_incluye_en_ingreso_mientras_el_snapshot_esta_abierto_y_lo_excluye_al_vencer(): void
    {
        $exam = $this->openEntryControl();

        $this->getJson(self::URL)->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_examen', $exam->id_examen)
            ->assertJsonPath('data.0.estado', Exam::EN_INGRESO);

        $this->travel(90)->minutes();

        $this->getJson(self::URL)->assertOk()->assertExactJson(['data' => []]);
        $this->assertSame(Exam::EN_INGRESO, $exam->fresh()->estado);
    }

    public function test_excluye_en_ingreso_sin_snapshot(): void
    {
        $this->createExam(['estado' => Exam::EN_INGRESO], [$this->aulaId]);

        $this->getJson(self::URL)->assertOk()->assertExactJson(['data' => []]);
    }

    public function test_docente_ve_solo_sus_examenes_y_no_comprueba_el_snapshot_de_otro_propietario(): void
    {
        $ownExam = $this->createExam(['estado' => Exam::EN_CURSO]);
        foreach ([Exam::EN_CURSO, Exam::EN_INGRESO] as $state) {
            $this->createExam(['estado' => $state, 'id_usuario_docente' => $this->otroDocenteId]);
        }
        $this->expectNoSnapshotRead();

        $this->getJson(self::URL)->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_examen', $ownExam->id_examen);
    }

    public function test_invitado_con_rol_vigente_consulta_solo_sus_examenes(): void
    {
        $this->endRole($this->docenteId);
        DB::table('usuario')->where('id_usuario', $this->docenteId)->update(['cod_sis' => 'INV4821']);
        $this->assignRole($this->docenteId, Role::INVITADO);
        $this->actAsUserId($this->docenteId);
        $ownExam = $this->createExam(['estado' => Exam::EN_CURSO]);
        $this->createExam(['estado' => Exam::EN_CURSO, 'id_usuario_docente' => $this->otroDocenteId]);

        $this->getJson(self::URL)->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_examen', $ownExam->id_examen);
    }

    public function test_auxiliar_habilitado_ve_solo_su_ambiente_asignado(): void
    {
        $exam = $this->examWithAssistants(['estado' => Exam::EN_CURSO], $this->otraAulaId);
        $this->actAsAssistant();

        $this->getJson(self::URL)->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_examen', $exam->id_examen)
            ->assertJsonPath('data.0.ambiente', ['id_ambiente' => $this->otraAulaId, 'nro_aula' => '692B']);
    }

    public function test_auxiliar_habilitado_sin_asignacion_ve_el_unico_ambiente_de_un_ingreso_abierto(): void
    {
        $exam = $this->examAtOpeningTime();
        $this->enableAssistant($exam);
        $this->actAsAssistant();
        app(RoomAssignmentService::class)->prepare($exam->id_examen);

        $this->getJson(self::URL)->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_examen', $exam->id_examen)
            ->assertJsonPath('data.0.ambiente', ['id_ambiente' => $this->aulaId, 'nro_aula' => '691A']);
        $this->assertDatabaseHas('examen_auxiliar', [
            'id_examen' => $exam->id_examen,
            'id_usuario' => $this->mariaId,
            'id_ambiente' => null,
        ]);
    }

    public function test_auxiliar_sin_ambiente_o_sin_habilitacion_no_accede_ni_consulta_snapshots(): void
    {
        $this->examWithAssistants(['estado' => Exam::EN_INGRESO]);
        $this->createExam(['estado' => Exam::EN_INGRESO], [$this->aulaId]);
        $examWithoutRooms = $this->createExam(['estado' => Exam::EN_INGRESO]);
        $this->enableAssistant($examWithoutRooms);
        $this->actAsAssistant();
        $this->expectNoSnapshotRead();

        $this->getJson(self::URL)->assertOk()->assertExactJson(['data' => []]);
    }

    public function test_no_acepta_ambiente_ajeno_ni_aplica_el_fallback_a_una_asignacion_invalida(): void
    {
        $exam = $this->createExam(['estado' => Exam::EN_INGRESO], [$this->aulaId]);
        $this->enableAssistant($exam, $this->aulaId);
        $this->actAsAssistant();
        $this->expectNoSnapshotRead();

        // La FK impide guardar esta inconsistencia; se simula al hidratar el registro, sin tocarla.
        ExamAssistant::retrieved(function (ExamAssistant $assignment): void {
            $assignment->id_ambiente = $this->otraAulaId;
        });

        $this->getJson(self::URL)->assertOk()->assertExactJson(['data' => []]);
    }

    public function test_una_habilitacion_no_concede_acceso_si_termino_el_rol_auxiliar(): void
    {
        $this->examWithAssistants(['estado' => Exam::EN_CURSO], $this->aulaId);
        $this->actAsAssistant();
        $this->endRole($this->mariaId);

        $this->getJson(self::URL)->assertForbidden()
            ->assertJson(fn (AssertableJson $json) => $json->missing('data')->etc());
    }

    public function test_rechaza_cuenta_inactiva_aunque_sea_propietaria(): void
    {
        $this->createExam(['estado' => Exam::EN_CURSO]);
        DB::table('usuario')->where('id_usuario', $this->docenteId)->update(['estado' => RecordStatus::INACTIVE]);
        $this->actAsUserId($this->docenteId);

        $this->getJson(self::URL)->assertForbidden()->assertJsonPath('motivo', 'cuenta_inactiva')
            ->assertJson(fn (AssertableJson $json) => $json->missing('data')->etc());
    }

    public function test_rechaza_una_cuenta_con_cambio_de_clave_pendiente(): void
    {
        DB::table('usuario')->where('id_usuario', $this->docenteId)->update(['debe_cambiar_contrasenia' => true]);
        $this->actAsUserId($this->docenteId);

        $this->getJson(self::URL)->assertForbidden()->assertJsonPath('motivo', 'cambio_de_clave_pendiente')
            ->assertJson(fn (AssertableJson $json) => $json->missing('data')->etc());
    }

    public function test_ser_administrador_o_propietario_no_otorga_acceso_sin_un_rol_permitido(): void
    {
        $this->createExam(['estado' => Exam::EN_CURSO]);
        $this->endRole($this->docenteId);
        $this->assignRole($this->docenteId, Role::ADMINISTRADOR);

        $this->getJson(self::URL)->assertForbidden()
            ->assertJson(fn (AssertableJson $json) => $json->missing('data')->etc());
    }

    public function test_rechaza_solicitudes_sin_autenticacion(): void
    {
        $this->actAsGuest();

        $this->getJson(self::URL)->assertUnauthorized()
            ->assertJson(fn (AssertableJson $json) => $json->missing('data')->etc());
    }

    public function test_el_catalogo_respeta_su_configuracion_de_acceso_independiente(): void
    {
        $this->createExam(['estado' => Exam::EN_CURSO]);
        config(['sciem.access.exam_attendance' => [Role::AUXILIAR]]);

        $this->getJson(self::URL)->assertForbidden();
    }

    /** @dataProvider excludedStates */
    public function test_excluye_estados_fuera_del_catalogo(string $state): void
    {
        $exam = $this->examAtOpeningTime($state);
        $snapshots = app(EntryControlSnapshotService::class);
        $snapshots->activate($exam->id_examen, $snapshots->stage($exam));
        $this->assertTrue($snapshots->ready($exam->id_examen));

        $this->getJson(self::URL)->assertOk()->assertExactJson(['data' => []]);
    }

    public static function excludedStates(): array
    {
        return [[Exam::PROGRAMADO], [Exam::FINALIZADO], [Exam::CANCELADO]];
    }

    public function test_excluye_convocatorias_de_postulantes_por_vinculo_y_no_por_categoria(): void
    {
        $type = $this->examTypeId('ADMISION');
        $academic = $this->createExam(['estado' => Exam::EN_CURSO, 'id_tipo_examen' => $type]);
        $applicants = $this->createExam([
            'estado' => Exam::EN_CURSO,
            'id_tipo_examen' => $type,
            'id_carrera' => null,
            'id_materia' => null,
        ]);
        $academic->groups()->attach($this->grupoPropioId);
        $call = AdmissionCall::create([
            'id_facultad' => $this->facultadId,
            'id_gestion_periodo' => $this->periodoActivoId,
            'nro_opciones' => '1',
            'estado' => RecordStatus::ACTIVE,
        ]);
        $applicants->admissionExam()->create(['id_admision' => $call->id_admision, 'nro_opcion' => '1']);

        $this->getJson(self::URL)->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_examen', $academic->id_examen);
    }

    public function test_cuando_no_hay_examenes_devuelve_un_arreglo_vacio(): void
    {
        $this->getJson(self::URL)->assertOk()->assertExactJson(['data' => []]);
    }

    public function test_ordena_por_fecha_hora_e_identificador_y_respeta_campos_nulos(): void
    {
        $laterDate = $this->createExam(['estado' => Exam::EN_CURSO, 'fecha' => $this->futureDate(8)]);
        $laterTime = $this->createExam(['estado' => Exam::EN_CURSO, 'hora_inicio' => '10:00']);
        $first = $this->createExam([
            'estado' => Exam::EN_CURSO,
            'hora_fin' => null,
            'id_materia' => null,
            'id_carrera' => null,
        ]);
        $second = $this->createExam(['estado' => Exam::EN_CURSO]);

        $response = $this->getJson(self::URL)->assertOk()
            ->assertJsonPath('data.0.hora_fin', null)
            ->assertJsonPath('data.0.materia', null)
            ->assertJsonPath('data.0.grupos', []);
        $this->assertSame(
            [$first->id_examen, $second->id_examen, $laterTime->id_examen, $laterDate->id_examen],
            array_column($response->json('data'), 'id_examen')
        );
    }

    public function test_consultar_no_modifica_el_examen_asistencia_ni_presencia(): void
    {
        $exam = $this->openEntryControl();
        $studentId = DB::table('grupo_estudiante')->where('id_grupo', $this->grupoPropioId)
            ->orderBy('id_estudiante')->value('id_estudiante');
        StudentExam::create([
            'id_examen' => $exam->id_examen,
            'id_estudiante' => $studentId,
            'id_grupo' => $this->grupoPropioId,
            'id_ambiente' => $this->aulaId,
            'id_usuario_controlador' => $this->docenteId,
            'registrado_en' => now(),
            'estado_habilitacion' => StudentExam::HABILITADO,
            'estado_ingreso' => StudentExam::INGRESO,
            'hora_ingreso' => '08:00',
        ]);
        $attendance = DB::table('examen_estudiante')->where('id_examen', $exam->id_examen)->orderBy('id_estudiante');
        $originalAttendance = $attendance->get()->all();
        $originalExam = $exam->fresh()->getAttributes();
        $cache = Cache::store(config('sciem.entry_control_cache_store'));
        $presenceKey = "exam_{$exam->id_examen}_connected_users";
        $presence = [$this->otroDocenteId => ['nombre' => 'Docente de prueba', 'ultimo_latido' => now()->timestamp]];
        $cache->put($presenceKey, $presence, 30);

        $this->getJson(self::URL)->assertOk()->assertJsonCount(1, 'data');

        $this->assertEquals($originalAttendance, $attendance->get()->all());
        $this->assertSame($originalExam, $exam->fresh()->getAttributes());
        $this->assertSame($presence, $cache->get($presenceKey));
    }

    public function test_falla_de_redis_devuelve_503_sin_datos_parciales_ni_detalles_internos(): void
    {
        $this->createExam(['estado' => Exam::EN_CURSO]);
        $exam = $this->createExam(['estado' => Exam::EN_INGRESO, 'hora_inicio' => '10:00']);
        $exception = new ConnectionException($this->createMock(NodeConnectionInterface::class), 'Detalle interno');
        $this->mock(EntryControlSnapshotService::class, function (MockInterface $mock) use ($exam, $exception): void {
            $mock->shouldReceive('ready')->once()->with($exam->id_examen)->andThrow($exception);
        });

        $this->getJson(self::URL)->assertStatus(503)->assertExactJson([
            'message' => 'No se pudo comprobar la apertura del control de ingreso.',
        ]);
    }

    public function test_no_convierte_un_error_inesperado_en_un_503(): void
    {
        $exam = $this->createExam(['estado' => Exam::EN_INGRESO]);
        $this->mock(EntryControlSnapshotService::class, function (MockInterface $mock) use ($exam): void {
            $mock->shouldReceive('ready')->once()->with($exam->id_examen)->andThrow(new LogicException());
        });

        $this->withoutExceptionHandling();
        $this->expectException(LogicException::class);
        $this->getJson(self::URL);
    }

    private function openEntryControl(): Exam
    {
        $exam = $this->examAtOpeningTime();
        app(RoomAssignmentService::class)->prepare($exam->id_examen);

        return $exam->refresh();
    }

    private function examAtOpeningTime(string $state = Exam::PROGRAMADO): Exam
    {
        $exam = $this->createExam(['estado' => $state], [$this->aulaId]);
        $exam->groups()->attach($this->grupoPropioId);
        $this->travelTo(Carbon::parse($exam->fecha->toDateString() . ' 08:00', config('sciem.zona_horaria')));

        return $exam;
    }

    private function enableAssistant(Exam $exam, ?int $classroomId = null): void
    {
        ExamAssistant::create([
            'id_examen' => $exam->id_examen,
            'id_usuario' => $this->mariaId,
            'id_usuario_docente_habilita' => $this->docenteId,
            'id_ambiente' => $classroomId,
        ]);
    }

    private function actAsAssistant(): void
    {
        $this->assignRole($this->mariaId, Role::AUXILIAR);
        $this->actAsUserId($this->mariaId);
    }

    private function endRole(string $actorId): void
    {
        DB::table('usuario_rol')->where('id_usuario', $actorId)->whereNull('fecha_fin')->update(['fecha_fin' => now()]);
    }

    private function expectNoSnapshotRead(): void
    {
        $this->mock(EntryControlSnapshotService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('ready');
        });
    }
}
