<?php

namespace App\Services\EntryControl;

use App\Exceptions\EntryControl\LateEntryException;
use App\Models\Exam;
use App\Models\LateEntryRequest;
use App\Models\Student;
use App\Models\StudentExam;
use App\Models\User;
use App\Services\Security\AuditLogService;
use App\Support\SisCode;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Ingreso con retraso de un estudiante esperado mientras el examen está EN_CURSO (HU-037).
 * El auxiliar solicita, el docente autoriza o rechaza, y el docente también puede registrar
 * directamente. La verificación es la misma de HU-032 (VerificationService::evaluate) sobre
 * el registro armado desde PostgreSQL, porque el snapshot de Redis ya no está activo.
 *
 * Todas las escrituras bloquean en el mismo orden —examen, solicitud, examen_estudiante—,
 * el mismo que usan el inicio, la finalización y la confirmación de ingreso.
 */
class LateEntryService
{
    public const REQUEST_ACTION = 'SOLICITAR_INGRESO_RETRASO';
    public const AUTHORIZE_ACTION = 'AUTORIZAR_INGRESO_RETRASO';
    public const REJECT_ACTION = 'RECHAZAR_INGRESO_RETRASO';
    public const REGISTER_ACTION = 'REGISTRAR_INGRESO_RETRASO';

    public const ALREADY_ENTERED_MESSAGE = 'El estudiante ya registró su ingreso.';

    private const UNIQUE_VIOLATION = '23505';

    private const RELATIONS = ['student', 'classroom', 'requester', 'resolver'];

    private LateEntryAccessService $access;
    private EntryControlSnapshotService $snapshots;
    private IdentificationService $identification;
    private VerificationService $verification;
    private AuditLogService $auditLog;

    public function __construct(
        LateEntryAccessService $access,
        EntryControlSnapshotService $snapshots,
        IdentificationService $identification,
        VerificationService $verification,
        AuditLogService $auditLog
    ) {
        $this->access = $access;
        $this->snapshots = $snapshots;
        $this->identification = $identification;
        $this->verification = $verification;
        $this->auditLog = $auditLog;
    }

    /**
     * Verifica por código SIS y CI sin escribir nada. Devuelve el resultado de HU-032 y la
     * solicitud pendiente del estudiante, si la hay, para que la vista la resuelva ahí mismo.
     *
     * @param array{cod_sis: string, ci?: string|null, id_ambiente?: int|null} $input
     * @return array{
     *     verificacion: array{
     *         veredicto: string, motivo: string, autorizado: bool,
     *         estudiante: array{id_estudiante: int, cod_sis: string, ci: string|null, ci_pendiente: bool,
     *             nombre_completo: string, carrera: string|null, foto_url: string|null}|null,
     *         auxiliar: null,
     *         grupo: array{id_grupo: int, num_grupo: string}|null,
     *         ambiente_asignado: array{id_ambiente: int, nro_aula: string}|null,
     *         antecedentes: array{total: int, latest: array{tipo_falta: string, fecha_registro: string}|null},
     *         ingreso_previo: array{registrado_en: string|null, hora_ingreso: string|null,
     *             id_ambiente: int|null, nro_aula: string|null, controlador: string}|null
     *     },
     *     solicitud_pendiente: LateEntryRequest|null
     * }
     */
    public function verify(Exam $exam, array $input, User $actor): array
    {
        $access = $this->access->resolve($exam, $actor);
        $this->assertInProgress($exam);
        $roomId = $this->access->controlRoom(
            $exam,
            $access,
            isset($input['id_ambiente']) ? (int) $input['id_ambiente'] : null
        );

        $studentId = Student::query()->where('cod_sis', SisCode::normalize($input['cod_sis']))->value('id_estudiante');
        $record = $studentId === null ? null : $this->recordFor($exam, (int) $studentId);

        if ($record === null) {
            return ['verificacion' => $this->verification->notFoundResult(), 'solicitud_pendiente' => null];
        }

        return [
            'verificacion' => $this->verification->evaluate(
                $this->verification->withBackground($record),
                $input,
                $roomId
            ),
            'solicitud_pendiente' => $this->pendingQuery($exam, (int) $studentId)->with(self::RELATIONS)->first(),
        ];
    }

    /**
     * El auxiliar pide registrar al estudiante en su ambiente. La hora de la solicitud es
     * la que quedará como hora de ingreso si el docente la autoriza.
     *
     * @param array{id_estudiante: int, ci?: string|null, id_ambiente: int} $input
     */
    public function request(Exam $exam, array $input, User $actor): LateEntryRequest
    {
        try {
            $request = DB::transaction(function () use ($exam, $input, $actor): LateEntryRequest {
                $locked = $this->lockExam($exam);
                $access = $this->access->resolve($locked, $actor);
                $this->access->assertAssistant($access);
                $this->assertInProgress($locked);

                $roomId = $this->access->controlRoom($locked, $access, (int) $input['id_ambiente']);
                $studentId = (int) $input['id_estudiante'];
                $this->allowedRecord($this->recordFor($locked, $studentId), $input, $roomId);
                $this->assertNoPendingRequest($locked, $studentId);
                $this->lockEntry($locked, $studentId);

                $request = LateEntryRequest::create([
                    'id_examen' => $locked->id_examen,
                    'id_estudiante' => $studentId,
                    'id_ambiente' => $roomId,
                    'id_usuario_solicitante' => $actor->id_usuario,
                    'estado' => LateEntryRequest::PENDIENTE,
                    'solicitado_en' => Carbon::now('UTC'),
                ]);

                $this->auditLog->registrar(self::REQUEST_ACTION, $request->getTable(), null, [
                    'id_solicitud' => $request->id_solicitud,
                    'id_examen' => (int) $locked->id_examen,
                    'id_estudiante' => $studentId,
                    'id_ambiente' => $roomId,
                    'estado' => LateEntryRequest::PENDIENTE,
                ], $actor->id_usuario);

                return $request;
            });
        } catch (QueryException $exception) {
            // Dos solicitudes simultáneas: el índice parcial deja pasar solo una.
            if ($exception->getCode() !== self::UNIQUE_VIOLATION) {
                throw $exception;
            }

            throw LateEntryException::pendingRequest();
        }

        return $request->load(self::RELATIONS);
    }

    /** El docente autoriza: el estudiante queda CON_RETRASO a la hora de la solicitud. */
    public function authorize(Exam $exam, int $requestId, User $actor): LateEntryRequest
    {
        $request = $this->writeEntryOrConflict(function () use ($exam, $requestId, $actor): LateEntryRequest {
            $locked = $this->lockExam($exam);
            $this->access->assertTeacher($this->access->resolve($locked, $actor));
            $this->assertInProgress($locked);
            $request = $this->lockPendingRequest($locked, $requestId);

            $studentId = (int) $request->id_estudiante;
            $record = $this->allowedRecord($this->recordFor($locked, $studentId), [], null);
            $existing = $this->lockEntry($locked, $studentId);
            $resolvedAt = Carbon::now('UTC');
            $entryTime = $this->writeEntry(
                $locked,
                $record,
                $existing,
                (int) $request->id_ambiente,
                (string) $request->id_usuario_solicitante,
                $request->solicitado_en,
                $resolvedAt
            );

            $this->resolve($request, LateEntryRequest::AUTORIZADA, $actor, $resolvedAt);
            $this->auditLog->registrar(
                self::AUTHORIZE_ACTION,
                $request->getTable(),
                ['id_solicitud' => $request->id_solicitud, 'estado' => LateEntryRequest::PENDIENTE],
                [
                    'id_solicitud' => $request->id_solicitud,
                    'estado' => LateEntryRequest::AUTORIZADA,
                    'id_estudiante' => $studentId,
                    'estado_ingreso' => StudentExam::CON_RETRASO,
                    'hora_ingreso' => $entryTime,
                ],
                $actor->id_usuario
            );

            return $request;
        });

        return $request->load(self::RELATIONS);
    }

    /** El docente rechaza: el estudiante sigue ausente y no se escribe ningún ingreso. */
    public function reject(Exam $exam, int $requestId, User $actor): LateEntryRequest
    {
        $request = DB::transaction(function () use ($exam, $requestId, $actor): LateEntryRequest {
            $locked = $this->lockExam($exam);
            $this->access->assertTeacher($this->access->resolve($locked, $actor));
            $this->assertInProgress($locked);
            $request = $this->lockPendingRequest($locked, $requestId);

            $this->resolve($request, LateEntryRequest::RECHAZADA, $actor, Carbon::now('UTC'));
            $this->auditLog->registrar(
                self::REJECT_ACTION,
                $request->getTable(),
                ['id_solicitud' => $request->id_solicitud, 'estado' => LateEntryRequest::PENDIENTE],
                ['id_solicitud' => $request->id_solicitud, 'estado' => LateEntryRequest::RECHAZADA],
                $actor->id_usuario
            );

            return $request;
        });

        return $request->load(self::RELATIONS);
    }

    /**
     * El docente registra directamente, sin solicitud, en el ambiente que elige.
     *
     * @param array{id_estudiante: int, ci?: string|null, id_ambiente: int} $input
     * @return array{id_estudiante: int, estado_ingreso: string, hora_ingreso: string, id_ambiente: int}
     */
    public function register(Exam $exam, array $input, User $actor): array
    {
        return $this->writeEntryOrConflict(function () use ($exam, $input, $actor): array {
            $locked = $this->lockExam($exam);
            $this->access->assertTeacher($this->access->resolve($locked, $actor));
            $this->assertInProgress($locked);

            $roomId = (int) $input['id_ambiente'];
            $this->access->assertRoomInExam($locked, $roomId);
            $studentId = (int) $input['id_estudiante'];
            $record = $this->allowedRecord($this->recordFor($locked, $studentId), $input, null);
            $this->assertNoPendingRequest($locked, $studentId);
            $existing = $this->lockEntry($locked, $studentId);

            $now = Carbon::now('UTC');
            $entryTime = $this->writeEntry($locked, $record, $existing, $roomId, $actor->id_usuario, $now, $now);

            $this->auditLog->registrar(self::REGISTER_ACTION, $this->entryTable(), null, [
                'id_examen' => (int) $locked->id_examen,
                'id_estudiante' => $studentId,
                'estado_ingreso' => StudentExam::CON_RETRASO,
                'hora_ingreso' => $entryTime,
                'id_ambiente' => $roomId,
            ], $actor->id_usuario);

            return [
                'id_estudiante' => $studentId,
                'estado_ingreso' => StudentExam::CON_RETRASO,
                'hora_ingreso' => $entryTime,
                'id_ambiente' => $roomId,
            ];
        });
    }

    /**
     * Ejecuta la escritura del ingreso en una transacción. Dos ingresos simultáneos del mismo
     * estudiante chocan en la clave de examen_estudiante: el segundo es un duplicado.
     *
     * @template TResult
     * @param callable(): TResult $write
     * @return TResult
     */
    private function writeEntryOrConflict(callable $write)
    {
        try {
            return DB::transaction($write);
        } catch (QueryException $exception) {
            if ($exception->getCode() !== self::UNIQUE_VIOLATION) {
                throw $exception;
            }

            throw LateEntryException::denied(VerificationService::DUPLICATE, self::ALREADY_ENTERED_MESSAGE);
        }
    }

    private function lockExam(Exam $exam): Exam
    {
        return Exam::query()->whereKey($exam->id_examen)->lockForUpdate()->firstOrFail();
    }

    private function assertInProgress(Exam $exam): void
    {
        if ($exam->estado !== Exam::EN_CURSO) {
            throw LateEntryException::examNotInProgress();
        }
    }

    private function lockPendingRequest(Exam $exam, int $requestId): LateEntryRequest
    {
        $request = LateEntryRequest::query()
            ->where('id_examen', $exam->id_examen)
            ->whereKey($requestId)
            ->lockForUpdate()
            ->firstOrFail();

        if ($request->estado !== LateEntryRequest::PENDIENTE) {
            throw LateEntryException::requestResolved();
        }

        return $request;
    }

    private function assertNoPendingRequest(Exam $exam, int $studentId): void
    {
        if ($this->pendingQuery($exam, $studentId)->lockForUpdate()->first(['id_solicitud']) !== null) {
            throw LateEntryException::pendingRequest();
        }
    }

    private function pendingQuery(Exam $exam, int $studentId): Builder
    {
        return LateEntryRequest::query()
            ->where('id_examen', $exam->id_examen)
            ->where('id_estudiante', $studentId)
            ->where('estado', LateEntryRequest::PENDIENTE);
    }

    /** Fila de ingreso del estudiante, bloqueada; null si todavía no tiene. */
    private function lockEntry(Exam $exam, int $studentId): ?object
    {
        $entry = DB::table($this->entryTable())
            ->where('id_examen', $exam->id_examen)
            ->where('id_estudiante', $studentId)
            ->lockForUpdate()
            ->first(['estado_ingreso']);

        if ($entry !== null && in_array(
            $entry->estado_ingreso,
            [StudentExam::INGRESO, StudentExam::CON_RETRASO],
            true
        )) {
            throw LateEntryException::denied(VerificationService::DUPLICATE, self::ALREADY_ENTERED_MESSAGE);
        }

        return $entry;
    }

    /**
     * Registro del estudiante de la nómina, o el del estudiante ajeno para que la
     * verificación responda «No pertenece». Null si el estudiante no existe.
     *
     * @return array{
     *     estudiante: array{id_estudiante: int, cod_sis: string, ci: string|null, ci_pendiente: bool,
     *         nombre_completo: string, carrera: string|null, foto_url: string|null},
     *     nombre?: string, apellido_paterno?: string|null, apellido_materno?: string|null,
     *     grupo: array{id_grupo: int, num_grupo: string}|null,
     *     estado_habilitacion: string|null,
     *     ambiente_asignado: array{id_ambiente: int, nro_aula: string}|null,
     *     antecedentes?: array{total: int, latest: array{tipo_falta: string, fecha_registro: string}|null},
     *     ingreso_previo: array{registrado_en: string|null, hora_ingreso: string|null,
     *         id_ambiente: int|null, nro_aula: string|null, controlador: string}|null
     * }|null
     */
    private function recordFor(Exam $exam, int $studentId): ?array
    {
        return $this->snapshots->recordFor($exam, $studentId)
            ?? $this->identification->outsideStudentBy('id_estudiante', (string) $studentId);
    }

    /**
     * Aplica las reglas de HU-037 al escribir: si la verificación no autoriza, no se solicita
     * ni se registra nada.
     *
     * @param array{
     *     estudiante: array{id_estudiante: int, cod_sis: string, ci: string|null, ci_pendiente: bool,
     *         nombre_completo: string, carrera: string|null, foto_url: string|null},
     *     nombre?: string, apellido_paterno?: string|null, apellido_materno?: string|null,
     *     grupo: array{id_grupo: int, num_grupo: string}|null,
     *     estado_habilitacion: string|null,
     *     ambiente_asignado: array{id_ambiente: int, nro_aula: string}|null,
     *     antecedentes?: array{total: int, latest: array{tipo_falta: string, fecha_registro: string}|null},
     *     ingreso_previo: array{registrado_en: string|null, hora_ingreso: string|null,
     *         id_ambiente: int|null, nro_aula: string|null, controlador: string}|null
     * }|null $record
     * @param array{ci?: string|null} $input
     * @return array{
     *     estudiante: array{id_estudiante: int, cod_sis: string, ci: string|null, ci_pendiente: bool,
     *         nombre_completo: string, carrera: string|null, foto_url: string|null},
     *     grupo: array{id_grupo: int, num_grupo: string},
     *     estado_habilitacion: string|null,
     *     ambiente_asignado: array{id_ambiente: int, nro_aula: string}|null,
     *     ingreso_previo: null
     * }
     */
    private function allowedRecord(?array $record, array $input, ?int $roomId): array
    {
        if ($record === null) {
            throw LateEntryException::denied(VerificationService::NOT_FOUND, VerificationService::NOT_FOUND_MESSAGE);
        }

        $verdict = $this->verification->evaluate($record, $input, $roomId);

        if (! $verdict['autorizado']) {
            throw LateEntryException::denied($verdict['veredicto'], $verdict['motivo']);
        }

        return $record;
    }

    /**
     * Escribe CON_RETRASO. Si el estudiante ya tiene fila sin ingreso, la completa; si no,
     * la crea con el grupo del examen en que está inscrito. Devuelve la hora local guardada.
     *
     * @param array{
     *     estudiante: array{id_estudiante: int, cod_sis: string, ci: string|null, ci_pendiente: bool,
     *         nombre_completo: string, carrera: string|null, foto_url: string|null},
     *     grupo: array{id_grupo: int, num_grupo: string}
     * } $record
     */
    private function writeEntry(
        Exam $exam,
        array $record,
        ?object $existing,
        int $roomId,
        string $controllerId,
        Carbon $entryAt,
        Carbon $registeredAt
    ): string {
        $entryTime = $entryAt->copy()->setTimezone(config('sciem.zona_horaria'))->format('H:i:s');
        $attributes = [
            'estado_ingreso' => StudentExam::CON_RETRASO,
            'hora_ingreso' => $entryTime,
            'id_ambiente' => $roomId,
            'id_usuario_controlador' => $controllerId,
            'registrado_en' => $registeredAt->copy()->setTimezone('UTC')->format('Y-m-d H:i:sP'),
        ];
        $studentId = (int) $record['estudiante']['id_estudiante'];

        if ($existing === null) {
            DB::table($this->entryTable())->insert($attributes + [
                'id_examen' => $exam->id_examen,
                'id_estudiante' => $studentId,
                'id_grupo' => $record['grupo']['id_grupo'],
                'estado_habilitacion' => StudentExam::HABILITADO,
            ]);

            return $entryTime;
        }

        // estado_ingreso es NOT NULL: la fila bloqueada sin ingreso solo puede estar en NO_INGRESO.
        // Si no se actualiza exactamente esa fila, se revierte todo antes de resolver la solicitud.
        $updated = DB::table($this->entryTable())
            ->where('id_examen', $exam->id_examen)
            ->where('id_estudiante', $studentId)
            ->where('estado_ingreso', StudentExam::NO_INGRESO)
            ->update($attributes);

        if ($updated !== 1) {
            throw new LogicException('No se pudo registrar el ingreso con retraso del estudiante.');
        }

        return $entryTime;
    }

    private function entryTable(): string
    {
        return (new StudentExam())->getTable();
    }

    private function resolve(LateEntryRequest $request, string $state, User $actor, Carbon $resolvedAt): void
    {
        $request->update([
            'estado' => $state,
            'id_usuario_resolutor' => $actor->id_usuario,
            'resuelto_en' => $resolvedAt,
        ]);
    }
}
