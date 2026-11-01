<?php

namespace Tests\Feature\EntryControl;

use App\Exceptions\EntryControl\LateEntryException;
use App\Exceptions\Exams\ExamStateException;
use App\Models\Exam;
use App\Models\LateEntryRequest;
use App\Models\Role;
use App\Models\StudentExam;
use App\Models\User;
use App\Services\EntryControl\EntryControlSnapshotService;
use App\Services\EntryControl\LateEntryQueryService;
use App\Services\EntryControl\LateEntryService;
use App\Services\EntryControl\VerificationService;
use App\Services\Exams\ExamInProgressQueryService;
use App\Support\RecordStatus;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsExamCatalog;
use Tests\TestCase;

/**
 * Servicios del ingreso con retraso (HU-037), llamados directamente con el actor como
 * argumento. El examen tiene dos ambientes de capacidad 1: el primer estudiante de la
 * nómina corresponde a 691A y el segundo a 692B.
 */
class LateEntryServiceTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsExamCatalog;

    private const ASSISTANT_A = '44444444-4444-4444-8444-00000000000a';
    private const ASSISTANT_B = '44444444-4444-4444-8444-00000000000b';
    private const NOT_ENABLED_ASSISTANT = '44444444-4444-4444-8444-00000000000c';

    private Exam $exam;
    private int $firstStudentId;
    private int $secondStudentId;
    private User $teacher;
    private User $assistantA;
    private User $assistantB;
    private LateEntryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedExamCatalog();

        $this->exam = $this->createExam(['estado' => Exam::EN_CURSO], [$this->aulaId, $this->otraAulaId]);
        DB::table('grupo_examen')->insert([
            'id_examen' => $this->exam->id_examen,
            'id_grupo' => $this->grupoPropioId,
        ]);
        DB::table('ambiente')
            ->whereIn('id_ambiente', [$this->aulaId, $this->otraAulaId])
            ->update(['capacidad' => 1]);

        [$this->firstStudentId, $this->secondStudentId] = DB::table('grupo_estudiante')
            ->where('id_grupo', $this->grupoPropioId)
            ->orderBy('id_estudiante')
            ->pluck('id_estudiante')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->teacher = User::query()->findOrFail($this->docenteId);
        $this->assistantA = $this->createAssistant(self::ASSISTANT_A, '202499001', $this->aulaId);
        $this->assistantB = $this->createAssistant(self::ASSISTANT_B, '202499002', $this->otraAulaId);
        $this->service = app(LateEntryService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function createAssistant(string $id, string $sis, ?int $roomId, bool $enabled = true): User
    {
        DB::table('usuario')->insert([
            'id_usuario' => $id,
            'nombre' => 'Auxiliar',
            'apellido_paterno' => $sis,
            'correo' => "{$sis}@umss.edu",
            'contrasenia' => 'x',
            'cod_sis' => $sis,
            'estado' => RecordStatus::ACTIVE,
        ]);
        DB::table('usuario_rol')->insert([
            'id_usuario' => $id,
            'id_rol' => Role::query()->where('nombre_rol', Role::AUXILIAR)->value('id_rol'),
            'fecha_inicio' => now(),
        ]);

        if ($enabled) {
            DB::table('examen_auxiliar')->insert([
                'id_examen' => $this->exam->id_examen,
                'id_usuario' => $id,
                'id_usuario_docente_habilita' => $this->docenteId,
                'id_ambiente' => $roomId,
            ]);
        }

        return User::query()->findOrFail($id);
    }

    private function travelToLocal(string $time): void
    {
        Carbon::setTestNow(Carbon::parse("2026-10-31 {$time}:00", config('sciem.zona_horaria')));
    }

    private function ciOf(int $studentId): string
    {
        return (string) DB::table('estudiante')->where('id_estudiante', $studentId)->value('ci');
    }

    private function sisOf(int $studentId): string
    {
        return (string) DB::table('estudiante')->where('id_estudiante', $studentId)->value('cod_sis');
    }

    /** Solicitud del auxiliar de 692B por el segundo estudiante, que corresponde a ese ambiente. */
    private function requestSecondStudent(): LateEntryRequest
    {
        return $this->service->request($this->exam, [
            'id_estudiante' => $this->secondStudentId,
            'ci' => $this->ciOf($this->secondStudentId),
            'id_ambiente' => $this->otraAulaId,
        ], $this->assistantB);
    }

    private function entryOf(int $studentId): ?object
    {
        return DB::table('examen_estudiante')
            ->where('id_examen', $this->exam->id_examen)
            ->where('id_estudiante', $studentId)
            ->first();
    }

    private function setExamState(string $state): void
    {
        DB::table('examen')->where('id_examen', $this->exam->id_examen)->update(['estado' => $state]);
        $this->exam->refresh();
    }

    private function auditCount(string $action): int
    {
        return DB::table('log')
            ->join('accion', 'accion.id_accion', '=', 'log.id_accion')
            ->where('accion.operacion', $action)
            ->count();
    }

    private function assertLateEntryError(string $verdict, int $status, callable $operation): void
    {
        try {
            $operation();
        } catch (LateEntryException $exception) {
            $this->assertSame($verdict, $exception->verdict());
            $this->assertSame($status, $exception->status());
            return;
        }

        $this->fail("Se esperaba el veredicto {$verdict}.");
    }

    private function assertForbidden(callable $operation): void
    {
        try {
            $operation();
        } catch (AuthorizationException $exception) {
            $this->addToAssertionCount(1);
            return;
        }

        $this->fail('Se esperaba un 403.');
    }

    public function test_el_auxiliar_solicita_para_su_ambiente_y_no_registra_ingreso(): void
    {
        $this->travelToLocal('10:12');

        $request = $this->requestSecondStudent();

        $this->assertSame(LateEntryRequest::PENDIENTE, $request->estado);
        $this->assertSame($this->otraAulaId, $request->id_ambiente);
        $this->assertSame(self::ASSISTANT_B, $request->id_usuario_solicitante);
        $this->assertSame(
            '10:12',
            $request->solicitado_en->copy()->setTimezone(config('sciem.zona_horaria'))->format('H:i')
        );
        $this->assertNull($this->entryOf($this->secondStudentId));
    }

    public function test_autorizar_registra_con_retraso_a_la_hora_de_la_solicitud(): void
    {
        $this->travelToLocal('10:12');
        $request = $this->requestSecondStudent();

        $this->travelToLocal('10:20');
        $authorized = $this->service->authorize($this->exam, $request->id_solicitud, $this->teacher);

        $entry = $this->entryOf($this->secondStudentId);
        $this->assertSame(StudentExam::CON_RETRASO, $entry->estado_ingreso);
        $this->assertSame('10:12:00', $entry->hora_ingreso);
        $this->assertSame($this->otraAulaId, (int) $entry->id_ambiente);
        $this->assertSame(self::ASSISTANT_B, $entry->id_usuario_controlador);
        $this->assertSame(LateEntryRequest::AUTORIZADA, $authorized->estado);
        $this->assertSame($this->docenteId, $authorized->id_usuario_resolutor);
        $this->assertNotNull($authorized->resuelto_en);
    }

    public function test_autorizar_actualiza_los_totales_de_en_curso(): void
    {
        $inProgress = app(ExamInProgressQueryService::class);
        $before = $inProgress->show($this->exam->fresh(), $this->teacher)['totales'];

        $this->travelToLocal('10:12');
        $request = $this->requestSecondStudent();
        $this->service->authorize($this->exam, $request->id_solicitud, $this->teacher);

        $after = $inProgress->show($this->exam->fresh(), $this->teacher);
        $student = collect($after['estudiantes'])->firstWhere('id_estudiante', $this->secondStudentId);

        $this->assertSame($before['con_retraso'] + 1, $after['totales']['con_retraso']);
        $this->assertSame($before['ausentes'] - 1, $after['totales']['ausentes']);
        $this->assertSame(StudentExam::CON_RETRASO, $student['estado_ingreso']);
        $this->assertSame('10:12', $student['hora_ingreso']);
        $this->assertSame('692B', $student['ambiente']);
    }

    public function test_rechazar_deja_al_estudiante_ausente(): void
    {
        $request = $this->requestSecondStudent();

        $rejected = $this->service->reject($this->exam, $request->id_solicitud, $this->teacher);

        $this->assertSame(LateEntryRequest::RECHAZADA, $rejected->estado);
        $this->assertSame($this->docenteId, $rejected->id_usuario_resolutor);
        $this->assertNull($this->entryOf($this->secondStudentId));
        $this->assertSame(
            2,
            app(ExamInProgressQueryService::class)->show($this->exam->fresh(), $this->teacher)['totales']['ausentes']
        );
    }

    public function test_el_docente_registra_directamente_en_el_ambiente_que_elige(): void
    {
        $this->travelToLocal('10:30');

        $result = $this->service->register($this->exam, [
            'id_estudiante' => $this->secondStudentId,
            'ci' => $this->ciOf($this->secondStudentId),
            'id_ambiente' => $this->aulaId,
        ], $this->teacher);

        $entry = $this->entryOf($this->secondStudentId);
        $this->assertSame(StudentExam::CON_RETRASO, $result['estado_ingreso']);
        $this->assertSame('10:30:00', $entry->hora_ingreso);
        $this->assertSame($this->aulaId, (int) $entry->id_ambiente);
        $this->assertSame($this->docenteId, $entry->id_usuario_controlador);
        $this->assertSame(0, LateEntryRequest::query()->count());
    }

    private function insertAbsentRow(int $studentId): void
    {
        DB::table('examen_estudiante')->insert([
            'id_examen' => $this->exam->id_examen,
            'id_estudiante' => $studentId,
            'id_grupo' => $this->grupoPropioId,
            'estado_habilitacion' => StudentExam::HABILITADO,
            'estado_ingreso' => StudentExam::NO_INGRESO,
            'hora_ingreso' => null,
        ]);
    }

    public function test_registrar_completa_la_fila_sin_ingreso(): void
    {
        $this->insertAbsentRow($this->firstStudentId);

        $this->service->register($this->exam, [
            'id_estudiante' => $this->firstStudentId,
            'id_ambiente' => $this->aulaId,
        ], $this->teacher);

        $this->assertSame(StudentExam::CON_RETRASO, $this->entryOf($this->firstStudentId)->estado_ingreso);
        $this->assertSame(1, DB::table('examen_estudiante')
            ->where('id_examen', $this->exam->id_examen)
            ->where('id_estudiante', $this->firstStudentId)
            ->count());
    }

    public function test_autorizar_completa_la_fila_sin_ingreso(): void
    {
        $this->insertAbsentRow($this->secondStudentId);
        $this->travelToLocal('10:12');
        $request = $this->requestSecondStudent();

        $this->service->authorize($this->exam, $request->id_solicitud, $this->teacher);

        $entry = $this->entryOf($this->secondStudentId);
        $this->assertSame(StudentExam::CON_RETRASO, $entry->estado_ingreso);
        $this->assertSame('10:12:00', $entry->hora_ingreso);
        $this->assertSame(LateEntryRequest::AUTORIZADA, $request->fresh()->estado);
    }

    /**
     * La única fila sin ingreso que puede existir está en NO_INGRESO: el esquema no admite
     * estado_ingreso nulo (el nulo de En curso es el del LEFT JOIN sin fila).
     */
    public function test_examen_estudiante_no_admite_estado_ingreso_nulo(): void
    {
        try {
            DB::transaction(fn () => DB::table('examen_estudiante')->insert([
                'id_examen' => $this->exam->id_examen,
                'id_estudiante' => $this->firstStudentId,
                'id_grupo' => $this->grupoPropioId,
                'estado_habilitacion' => StudentExam::HABILITADO,
                'estado_ingreso' => null,
            ]));
            $this->fail('El esquema debía rechazar estado_ingreso nulo.');
        } catch (QueryException $exception) {
            $this->assertSame('23502', $exception->getCode());
        }
    }

    public function test_verificar_normaliza_el_codigo_sis(): void
    {
        $result = $this->service->verify($this->exam, [
            'cod_sis' => ' ' . $this->sisOf($this->firstStudentId) . ' ',
        ], $this->teacher);

        $this->assertSame(VerificationService::AUTHORIZED, $result['verificacion']['veredicto']);
        $this->assertSame($this->firstStudentId, $result['verificacion']['estudiante']['id_estudiante']);
    }

    public function test_verificar_devuelve_el_resultado_de_hu032_y_la_solicitud_pendiente(): void
    {
        $pending = $this->requestSecondStudent();

        $forTeacher = $this->service->verify($this->exam, [
            'cod_sis' => $this->sisOf($this->secondStudentId),
            'ci' => $this->ciOf($this->secondStudentId),
        ], $this->teacher);
        $forAssistant = $this->service->verify($this->exam, [
            'cod_sis' => $this->sisOf($this->firstStudentId),
            'id_ambiente' => $this->otraAulaId,
        ], $this->assistantB);
        $unknown = $this->service->verify($this->exam, ['cod_sis' => '999999999'], $this->teacher);

        $this->assertSame(VerificationService::AUTHORIZED, $forTeacher['verificacion']['veredicto']);
        $this->assertSame('692B', $forTeacher['verificacion']['ambiente_asignado']['nro_aula']);
        $this->assertSame($pending->id_solicitud, $forTeacher['solicitud_pendiente']->id_solicitud);
        $this->assertSame(VerificationService::WRONG_ROOM, $forAssistant['verificacion']['veredicto']);
        $this->assertSame('Corresponde al ambiente 691A', $forAssistant['verificacion']['motivo']);
        $this->assertNull($forAssistant['solicitud_pendiente']);
        $this->assertSame(app(VerificationService::class)->notFoundResult(), $unknown['verificacion']);
        $this->assertSame(VerificationService::NOT_FOUND_MESSAGE, $unknown['verificacion']['motivo']);
    }

    public function test_la_verificacion_rechaza_ci_distinto_y_estudiante_ajeno(): void
    {
        $this->enrollStudents($this->grupoAjenoId, 1);
        $outsiderId = (int) DB::table('grupo_estudiante')
            ->where('id_grupo', $this->grupoAjenoId)
            ->value('id_estudiante');

        $this->assertLateEntryError(VerificationService::CI_MISMATCH, 422, fn () => $this->service->request(
            $this->exam,
            ['id_estudiante' => $this->secondStudentId, 'ci' => '1', 'id_ambiente' => $this->otraAulaId],
            $this->assistantB
        ));
        $this->assertLateEntryError(VerificationService::CI_MISMATCH, 422, fn () => $this->service->register(
            $this->exam,
            ['id_estudiante' => $this->secondStudentId, 'ci' => '1', 'id_ambiente' => $this->otraAulaId],
            $this->teacher
        ));
        $this->assertLateEntryError(VerificationService::NOT_IN_EXAM, 422, fn () => $this->service->register(
            $this->exam,
            ['id_estudiante' => $outsiderId, 'id_ambiente' => $this->aulaId],
            $this->teacher
        ));
        $this->assertSame(0, LateEntryRequest::query()->count());
        $this->assertNull($this->entryOf($outsiderId));
    }

    public function test_el_auxiliar_no_solicita_por_un_estudiante_de_otro_ambiente(): void
    {
        $this->assertLateEntryError(VerificationService::WRONG_ROOM, 422, fn () => $this->service->request(
            $this->exam,
            ['id_estudiante' => $this->firstStudentId, 'id_ambiente' => $this->otraAulaId],
            $this->assistantB
        ));
    }

    public function test_un_estudiante_que_ya_ingreso_es_un_conflicto(): void
    {
        DB::table('examen_estudiante')->insert([
            'id_examen' => $this->exam->id_examen,
            'id_estudiante' => $this->secondStudentId,
            'id_grupo' => $this->grupoPropioId,
            'estado_habilitacion' => StudentExam::HABILITADO,
            'estado_ingreso' => StudentExam::INGRESO,
            'hora_ingreso' => '10:01:00',
            'id_ambiente' => $this->otraAulaId,
        ]);

        try {
            $this->requestSecondStudent();
            $this->fail('Se esperaba un duplicado.');
        } catch (LateEntryException $exception) {
            $this->assertSame(VerificationService::DUPLICATE, $exception->verdict());
            $this->assertSame(409, $exception->status());
            $this->assertSame('Ya ingresó a las 10:01 por el 692B', $exception->getMessage());
        }

        $this->assertLateEntryError(VerificationService::DUPLICATE, 409, fn () => $this->service->register(
            $this->exam,
            ['id_estudiante' => $this->secondStudentId, 'id_ambiente' => $this->otraAulaId],
            $this->teacher
        ));
    }

    public function test_un_examen_que_no_esta_en_curso_es_un_conflicto(): void
    {
        $request = $this->requestSecondStudent();

        foreach ([Exam::EN_INGRESO, Exam::FINALIZADO] as $state) {
            $this->setExamState($state);
            $conflict = LateEntryException::EXAM_NOT_IN_PROGRESS;

            $this->assertLateEntryError($conflict, 409, fn () => $this->service->verify(
                $this->exam,
                ['cod_sis' => $this->sisOf($this->firstStudentId)],
                $this->teacher
            ));
            $this->assertLateEntryError($conflict, 409, fn () => $this->service->request($this->exam, [
                'id_estudiante' => $this->firstStudentId,
                'id_ambiente' => $this->aulaId,
            ], $this->assistantA));
            $this->assertLateEntryError(
                $conflict,
                409,
                fn () => $this->service->authorize($this->exam, $request->id_solicitud, $this->teacher)
            );
            $this->assertLateEntryError(
                $conflict,
                409,
                fn () => $this->service->reject($this->exam, $request->id_solicitud, $this->teacher)
            );
            $this->assertLateEntryError($conflict, 409, fn () => $this->service->register($this->exam, [
                'id_estudiante' => $this->firstStudentId,
                'id_ambiente' => $this->aulaId,
            ], $this->teacher));
        }

        $this->assertSame(LateEntryRequest::PENDIENTE, $request->fresh()->estado);
        $this->assertNull($this->entryOf($this->secondStudentId));
    }

    public function test_una_solicitud_pendiente_impide_otra_y_el_registro_directo(): void
    {
        $this->requestSecondStudent();

        $this->assertLateEntryError(
            LateEntryException::PENDING_REQUEST,
            409,
            fn () => $this->requestSecondStudent()
        );
        $this->assertLateEntryError(LateEntryException::PENDING_REQUEST, 409, fn () => $this->service->register(
            $this->exam,
            ['id_estudiante' => $this->secondStudentId, 'id_ambiente' => $this->otraAulaId],
            $this->teacher
        ));
        $this->assertSame(1, LateEntryRequest::query()->count());
        $this->assertNull($this->entryOf($this->secondStudentId));
    }

    public function test_una_solicitud_resuelta_no_se_vuelve_a_resolver(): void
    {
        $request = $this->requestSecondStudent();
        $this->service->reject($this->exam, $request->id_solicitud, $this->teacher);

        $this->assertLateEntryError(
            LateEntryException::REQUEST_RESOLVED,
            409,
            fn () => $this->service->authorize($this->exam, $request->id_solicitud, $this->teacher)
        );
        $this->assertLateEntryError(
            LateEntryException::REQUEST_RESOLVED,
            409,
            fn () => $this->service->reject($this->exam, $request->id_solicitud, $this->teacher)
        );
        $this->assertNull($this->entryOf($this->secondStudentId));
    }

    /**
     * Dos autorizaciones de la misma solicitud: la segunda espera el bloqueo de la primera
     * y la encuentra resuelta. Con una sola conexión se reproduce en secuencia.
     */
    public function test_la_segunda_autorizacion_no_registra_otro_ingreso(): void
    {
        $request = $this->requestSecondStudent();

        $this->service->authorize($this->exam, $request->id_solicitud, $this->teacher);

        $this->assertLateEntryError(
            LateEntryException::REQUEST_RESOLVED,
            409,
            fn () => $this->service->authorize($this->exam, $request->id_solicitud, $this->teacher)
        );
        $this->assertSame(1, DB::table('examen_estudiante')
            ->where('id_examen', $this->exam->id_examen)
            ->where('id_estudiante', $this->secondStudentId)
            ->count());
        $this->assertSame(1, $this->auditCount(LateEntryService::AUTHORIZE_ACTION));
    }

    public function test_bloquea_examen_solicitud_estudiante_e_ingreso_en_ese_orden(): void
    {
        $request = $this->requestSecondStudent();
        $locked = [];
        DB::listen(function ($query) use (&$locked): void {
            if (preg_match('/^select .* from "(\w+)" .*for update$/i', $query->sql, $match)) {
                $locked[] = $match[1];
            }
        });

        $this->service->authorize($this->exam, $request->id_solicitud, $this->teacher);

        $this->assertSame(['examen', 'solicitud_ingreso_retraso', 'estudiante', 'examen_estudiante'], $locked);
    }

    public function test_la_solicitud_tambien_bloquea_en_el_mismo_orden(): void
    {
        $locked = [];
        DB::listen(function ($query) use (&$locked): void {
            if (preg_match('/^select .* from "(\w+)" .*for update$/i', $query->sql, $match)) {
                $locked[] = $match[1];
            }
        });

        $this->requestSecondStudent();

        $this->assertSame(['examen', 'solicitud_ingreso_retraso', 'estudiante', 'examen_estudiante'], $locked);
    }

    public function test_cada_operacion_deja_una_fila_en_la_bitacora(): void
    {
        $first = $this->requestSecondStudent();
        $this->service->authorize($this->exam, $first->id_solicitud, $this->teacher);
        $second = $this->service->request($this->exam, [
            'id_estudiante' => $this->firstStudentId,
            'id_ambiente' => $this->aulaId,
        ], $this->assistantA);
        $this->service->reject($this->exam, $second->id_solicitud, $this->teacher);
        $this->service->register($this->exam, [
            'id_estudiante' => $this->firstStudentId,
            'id_ambiente' => $this->aulaId,
        ], $this->teacher);

        foreach ([
            LateEntryService::REQUEST_ACTION => [2, 'solicitud_ingreso_retraso'],
            LateEntryService::AUTHORIZE_ACTION => [1, 'solicitud_ingreso_retraso'],
            LateEntryService::REJECT_ACTION => [1, 'solicitud_ingreso_retraso'],
            LateEntryService::REGISTER_ACTION => [1, 'examen_estudiante'],
        ] as $action => [$count, $table]) {
            $this->assertSame($count, $this->auditCount($action), $action);
            $this->assertSame($count, DB::table('log')
                ->join('accion', 'accion.id_accion', '=', 'log.id_accion')
                ->where('accion.operacion', $action)
                ->where('log.tabla_afectada', $table)
                ->count(), $action);
        }

        $this->assertSame(self::ASSISTANT_B, DB::table('log')
            ->join('accion', 'accion.id_accion', '=', 'log.id_accion')
            ->where('accion.operacion', LateEntryService::REQUEST_ACTION)
            ->orderBy('log.id_log')
            ->value('log.id_usuario'));
        $this->assertSame($this->docenteId, DB::table('log')
            ->join('accion', 'accion.id_accion', '=', 'log.id_accion')
            ->where('accion.operacion', LateEntryService::REGISTER_ACTION)
            ->value('log.id_usuario'));
    }

    public function test_solo_el_docente_propietario_resuelve_y_registra(): void
    {
        $request = $this->requestSecondStudent();
        $otherTeacher = User::query()->findOrFail($this->otroDocenteId);
        $register = ['id_estudiante' => $this->firstStudentId, 'id_ambiente' => $this->aulaId];

        $this->assertForbidden(fn () => $this->service->authorize($this->exam, $request->id_solicitud, $otherTeacher));
        $this->assertForbidden(fn () => $this->service->reject($this->exam, $request->id_solicitud, $otherTeacher));
        $this->assertForbidden(fn () => $this->service->register($this->exam, $register, $otherTeacher));
        $this->assertForbidden(
            fn () => $this->service->authorize($this->exam, $request->id_solicitud, $this->assistantB)
        );
        $this->assertForbidden(fn () => $this->service->reject($this->exam, $request->id_solicitud, $this->assistantA));
        $this->assertForbidden(fn () => $this->service->register($this->exam, $register, $this->assistantA));

        $this->assertSame(LateEntryRequest::PENDIENTE, $request->fresh()->estado);
    }

    public function test_solo_un_auxiliar_habilitado_solicita_y_solo_en_su_ambiente(): void
    {
        $notEnabled = $this->createAssistant(self::NOT_ENABLED_ASSISTANT, '202499003', null, false);
        $input = ['id_estudiante' => $this->secondStudentId, 'id_ambiente' => $this->otraAulaId];

        $this->assertForbidden(fn () => $this->service->request($this->exam, $input, $this->teacher));
        $this->assertForbidden(fn () => $this->service->request($this->exam, $input, $notEnabled));
        $this->assertForbidden(fn () => $this->service->request($this->exam, $input, $this->assistantA));
        $this->assertForbidden(fn () => $this->service->verify(
            $this->exam,
            ['cod_sis' => $this->sisOf($this->secondStudentId), 'id_ambiente' => $this->otraAulaId],
            $this->assistantA
        ));

        $this->assertSame(0, LateEntryRequest::query()->count());
    }

    public function test_un_auxiliar_sin_ambiente_en_un_examen_multiaula_no_controla(): void
    {
        DB::table('examen_auxiliar')
            ->where('id_examen', $this->exam->id_examen)
            ->where('id_usuario', self::ASSISTANT_B)
            ->update(['id_ambiente' => null]);

        $this->assertForbidden(fn () => $this->requestSecondStudent());
    }

    public function test_una_cuenta_inactiva_no_controla(): void
    {
        DB::table('usuario')->where('id_usuario', $this->docenteId)->update(['estado' => RecordStatus::INACTIVE]);

        $this->assertForbidden(fn () => $this->service->register($this->exam, [
            'id_estudiante' => $this->firstStudentId,
            'id_ambiente' => $this->aulaId,
        ], $this->teacher->fresh()));
    }

    public function test_el_docente_registra_solo_en_un_ambiente_del_examen(): void
    {
        $this->assertForbidden(fn () => $this->service->register($this->exam, [
            'id_estudiante' => $this->firstStudentId,
            'id_ambiente' => $this->aulaInactivaId,
        ], $this->teacher));
    }

    public function test_el_listado_muestra_al_docente_todo_y_al_auxiliar_lo_suyo(): void
    {
        $queries = app(LateEntryQueryService::class);
        $resolved = $this->requestSecondStudent();
        $this->service->reject($this->exam, $resolved->id_solicitud, $this->teacher);
        $pending = $this->service->request($this->exam, [
            'id_estudiante' => $this->firstStudentId,
            'id_ambiente' => $this->aulaId,
        ], $this->assistantA);

        $this->assertSame(
            [$pending->id_solicitud, $resolved->id_solicitud],
            $queries->list($this->exam, $this->teacher)['solicitudes']->pluck('id_solicitud')->all()
        );
        $this->assertSame(
            [$resolved->id_solicitud],
            $queries->list($this->exam, $this->assistantB)['solicitudes']->pluck('id_solicitud')->all()
        );
        $this->assertTrue(
            $queries->list($this->exam, $this->teacher)['solicitudes']->first()->relationLoaded('requester')
        );

        $this->setExamState(Exam::FINALIZADO);
        $this->assertCount(2, $queries->list($this->exam, $this->teacher)['solicitudes']);

        $this->setExamState(Exam::EN_INGRESO);
        $this->expectException(ExamStateException::class);
        $queries->list($this->exam, $this->teacher);
    }

    public function test_el_listado_exige_controlar_el_examen(): void
    {
        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage(EntryControlSnapshotService::CONTROL_DENIED_MESSAGE);

        app(LateEntryQueryService::class)->list($this->exam, User::query()->findOrFail($this->otroDocenteId));
    }
}
