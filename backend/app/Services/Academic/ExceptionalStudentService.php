<?php

namespace App\Services\Academic;

use App\Exceptions\Exams\ExamStateException;
use App\Models\Exam;
use App\Models\Student;
use App\Services\EntryControl\EntryControlSnapshotService;
use App\Services\Exams\ExamParticipantService;
use App\Services\Security\AuditLogService;
use App\Support\RecordStatus;
use App\Support\SisCode;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use stdClass;
use Throwable;

/**
 * Estudiantes excepcionales (HU-023): un estudiante que no figura en la lista de un examen
 * (convalidación, inscripción tardía aprobada) se agrega SOLO a la lista de ese examen.
 *
 * Se guarda en examen_estudiante con la marca es_excepcional: no toca el padrón del grupo
 * (grupo_estudiante) ni los demás exámenes del grupo. Solo el docente responsable del examen puede
 * registrarlo y solo mientras está EN_CURSO. Desde ahí se comporta como cualquier estudiante: la
 * fuente única de esperados (ExamRosterService) lo incluye y, si nunca ingresa, queda como ausente.
 */
class ExceptionalStudentService
{
    public const NOT_IN_PROGRESS_MESSAGE =
        'El examen ya no está en curso: no se pueden registrar estudiantes excepcionales.';
    public const ALREADY_LISTED_MESSAGE = 'Ya figura en la lista de estudiantes de este examen';
    public const NOT_FOUND_MESSAGE = 'No se encontró a ningún estudiante con este código';

    private ExamParticipantService $participants;

    private AuditLogService $auditLog;

    private EntryControlSnapshotService $snapshots;

    public function __construct(
        ExamParticipantService $participants,
        AuditLogService $auditLog,
        EntryControlSnapshotService $snapshots
    ) {
        $this->participants = $participants;
        $this->auditLog = $auditLog;
        $this->snapshots = $snapshots;
    }

    /**
     * Exámenes En curso del docente responsable, del más próximo al más lejano.
     *
     * @return array<int, stdClass>
     */
    public function teacherExams(string $teacherId): array
    {
        return Exam::query()
            ->where('id_usuario_docente', $teacherId)
            ->where('estado', Exam::EN_CURSO)
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
            ->orderBy('id_examen')
            ->get()
            ->map(static fn (Exam $exam): stdClass => (object) [
                'id_examen' => (int) $exam->id_examen,
                'nombre_examen' => (string) $exam->nombre_examen,
                'fecha' => $exam->fecha->format('Y-m-d'),
                'hora_inicio' => substr((string) $exam->hora_inicio, 0, 5),
                'estado' => (string) $exam->estado,
            ])
            ->values()
            ->all();
    }

    /**
     * Opciones válidas para el registro, siempre comprobadas de nuevo al guardar.
     *
     * @return array<string, array<int, array<string, int|string>>>
     * @throws AuthorizationException si el examen no es del docente
     */
    public function options(int $examId, string $teacherId): array
    {
        $this->responsibleExam($examId, $teacherId);

        $groups = DB::table('grupo_examen as gx')
            ->join('grupo as g', 'g.id_grupo', '=', 'gx.id_grupo')
            ->where('gx.id_examen', $examId)
            ->orderBy('g.num_grupo')
            ->get(['g.id_grupo', 'g.num_grupo']);

        $careers = DB::table('grupo_oferta as go')
            ->join('carrera as c', 'c.id_carrera', '=', 'go.id_carrera')
            ->whereIn('go.id_grupo', $groups->pluck('id_grupo')->all())
            ->orderBy('c.nombre')
            ->get(['go.id_grupo', 'c.id_carrera', 'c.nombre']);

        $rooms = DB::table('examen_ambiente as ea')
            ->join('ambiente as a', 'a.id_ambiente', '=', 'ea.id_ambiente')
            ->leftJoin('examen_estudiante as ee', function ($join) use ($examId): void {
                $join->on('ee.id_ambiente', '=', 'a.id_ambiente')
                    ->where('ee.id_examen', '=', $examId);
            })
            ->where('ea.id_examen', $examId)
            ->groupBy('a.id_ambiente', 'a.nro_aula', 'a.capacidad')
            ->orderBy('a.nro_aula')
            ->select('a.id_ambiente', 'a.nro_aula', 'a.capacidad')
            ->selectRaw('COUNT(ee.id_estudiante) AS ocupados')
            ->get();

        return [
            'grupos' => $groups->map(static fn ($group): array => [
                'id_grupo' => (int) $group->id_grupo,
                'nombre' => 'Grupo ' . $group->num_grupo,
            ])->all(),
            'carreras' => $careers->map(static fn ($career): array => [
                'id_grupo' => (int) $career->id_grupo,
                'id_carrera' => (int) $career->id_carrera,
                'nombre' => (string) $career->nombre,
            ])->all(),
            'ambientes' => $rooms->map(static fn ($room): array => [
                'id_ambiente' => (int) $room->id_ambiente,
                'nombre' => (string) $room->nro_aula,
                'capacidad' => (int) $room->capacidad,
                'ocupados' => (int) $room->ocupados,
            ])->all(),
        ];
    }

    /**
     * Busca al estudiante por código SIS, en la misma tabla que usa la carga de nómina.
     * Devuelve null si no hay un estudiante activo con ese código.
     *
     * @throws AuthorizationException si el examen no es del docente
     */
    public function search(int $examId, string $teacherId, string $sisCode): ?stdClass
    {
        $exam = $this->responsibleExam($examId, $teacherId);

        $student = Student::query()
            ->where('cod_sis', SisCode::normalize($sisCode))
            ->where('estado', RecordStatus::ACTIVE)
            ->first();

        if ($student === null) {
            return null;
        }

        return (object) [
            'id_estudiante' => (int) $student->id_estudiante,
            'cod_sis' => (string) $student->cod_sis,
            'nombre_completo' => $student->nombre_completo,
            'carrera' => $this->latestCareer((int) $student->id_estudiante),
            'ya_en_examen' => $this->isExpected((int) $exam->id_examen, (int) $student->id_estudiante),
        ];
    }

    /**
     * Agrega al estudiante a la lista del examen como excepcional. Una sola transacción con el
     * examen bloqueado: el estado y la lista se comprueban dentro del bloqueo, así dos registros
     * simultáneos del mismo estudiante no generan una segunda fila.
     *
     * @throws AuthorizationException si el examen no es del docente responsable
     * @throws ExamStateException si el examen ya no está En curso
     * @throws ValidationException si el estudiante no existe, ya está en la lista o los datos no corresponden
     */
    public function register(ExceptionalStudentData $data): stdClass
    {
        $this->responsibleExam($data->examId, $data->teacherId);

        $registered = DB::transaction(function () use ($data): stdClass {
            $exam = Exam::query()->whereKey($data->examId)->lockForUpdate()->firstOrFail();

            if ((string) $exam->id_usuario_docente !== $data->teacherId) {
                throw new AuthorizationException('No tiene acceso a este examen.');
            }

            if ($exam->estado !== Exam::EN_CURSO) {
                throw new ExamStateException(self::NOT_IN_PROGRESS_MESSAGE);
            }

            $student = Student::query()->where('estado', RecordStatus::ACTIVE)->find($data->studentId);

            if ($student === null || trim((string) $student->cod_sis) === '') {
                throw ValidationException::withMessages(['id_estudiante' => [self::NOT_FOUND_MESSAGE]]);
            }

            if ($this->isExpected($data->examId, $data->studentId)) {
                throw ValidationException::withMessages(['id_estudiante' => [self::ALREADY_LISTED_MESSAGE]]);
            }

            $this->assertChoicesBelongToExam($data);

            DB::table('examen_estudiante')->insert([
                'id_examen' => $data->examId,
                'id_estudiante' => $data->studentId,
                'id_grupo' => null,
                'estado_habilitacion' => 'HABILITADO',
                'estado_ingreso' => 'NO_INGRESO',
                'id_ambiente' => $data->roomId,
                'es_excepcional' => true,
                'motivo_excepcional' => $data->reason,
                'id_usuario_docente_excepcional' => $data->teacherId,
                'id_grupo_excepcional' => $data->groupId,
                'id_carrera_excepcional' => $data->careerId,
            ]);

            $this->auditLog->registrar(
                'REGISTRAR_ESTUDIANTE_EXCEPCIONAL',
                'examen_estudiante',
                null,
                [
                    'id_examen' => $data->examId,
                    'id_estudiante' => $data->studentId,
                    'id_grupo' => $data->groupId,
                    'id_carrera' => $data->careerId,
                    'id_ambiente' => $data->roomId,
                    'motivo' => $data->reason,
                ],
                $data->teacherId
            );

            return (object) [
                'id_examen' => $data->examId,
                'id_estudiante' => $data->studentId,
                'cod_sis' => (string) $student->cod_sis,
                'nombre_completo' => $student->nombre_completo,
                'carrera' => $this->latestCareer($data->studentId),
                'motivo_excepcional' => $data->reason,
                'fecha_registro' => now()->toIso8601String(),
            ];
        });

        $this->publishToEntryControl($data->examId, $data->studentId);

        return $registered;
    }

    /**
     * Suma al estudiante al estado operativo de Redis una vez confirmada la transacción. Si el control de
     * ingreso no está abierto no hace nada: el siguiente estado se arma desde PostgreSQL con él incluido.
     * Un fallo de Redis no deshace el registro, que ya está confirmado: se deja constancia en el log.
     */
    private function publishToEntryControl(int $examId, int $studentId): void
    {
        try {
            $this->snapshots->addStudent(Exam::query()->findOrFail($examId), $studentId);
        } catch (Throwable $error) {
            Log::error('HU-023: no se pudo agregar al excepcional al estado de ingreso en Redis', [
                'id_examen' => $examId,
                'id_estudiante' => $studentId,
                'error' => $error->getMessage(),
            ]);
        }
    }

    private function assertChoicesBelongToExam(ExceptionalStudentData $data): void
    {
        $inExam = DB::table('grupo_examen')
            ->where('id_examen', $data->examId)
            ->where('id_grupo', $data->groupId)
            ->exists();

        if (! $inExam) {
            throw ValidationException::withMessages(['id_grupo' => ['El grupo no pertenece al examen.']]);
        }

        $offered = DB::table('grupo_oferta')
            ->where('id_grupo', $data->groupId)
            ->where('id_carrera', $data->careerId)
            ->exists();

        if (! $offered) {
            throw ValidationException::withMessages([
                'id_carrera' => ['La carrera no se ofrece en el grupo seleccionado.'],
            ]);
        }

        $roomInExam = DB::table('examen_ambiente')
            ->where('id_examen', $data->examId)
            ->where('id_ambiente', $data->roomId)
            ->exists();

        if (! $roomInExam) {
            throw ValidationException::withMessages(['id_ambiente' => ['El ambiente no pertenece al examen.']]);
        }
    }

    /**
     * Solo el docente responsable del examen (id_usuario_docente) opera con excepcionales: un colaborador
     * o cualquier otro docente recibe 403; un examen inexistente, 404.
     */
    private function responsibleExam(int $examId, string $teacherId): Exam
    {
        $exam = Exam::query()->findOrFail($examId);

        if ((string) $exam->id_usuario_docente !== $teacherId) {
            throw new AuthorizationException('No tiene acceso a este examen.');
        }

        return $exam;
    }

    /** Si el estudiante ya figura en la lista de esperados del examen (nómina o excepcional). */
    private function isExpected(int $examId, int $studentId): bool
    {
        return DB::query()
            ->fromSub($this->participants->expected($examId), 'participantes')
            ->where('participantes.id_estudiante', $studentId)
            ->exists();
    }

    /**
     * Carrera de la inscripción más reciente del estudiante, o null si no tiene.
     *
     * @return array{id_carrera: int, nombre: string}|null
     */
    private function latestCareer(int $studentId): ?array
    {
        $row = DB::table('grupo_estudiante')
            ->join('carrera', 'carrera.id_carrera', '=', 'grupo_estudiante.id_carrera')
            ->where('grupo_estudiante.id_estudiante', $studentId)
            ->orderByDesc('grupo_estudiante.fecha_inscripcion')
            ->orderByDesc('grupo_estudiante.id_grupo')
            ->first(['carrera.id_carrera', 'carrera.nombre']);

        if ($row === null) {
            return null;
        }

        return [
            'id_carrera' => (int) $row->id_carrera,
            'nombre' => (string) $row->nombre,
        ];
    }
}
