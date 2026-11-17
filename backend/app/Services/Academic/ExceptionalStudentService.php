<?php

namespace App\Services\Academic;

use App\Exceptions\Exams\ExamStateException;
use App\Models\Exam;
use App\Models\Student;
use App\Services\Exams\ExamParticipantService;
use App\Services\Security\AuditLogService;
use App\Support\RecordStatus;
use App\Support\SisCode;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * Estudiantes excepcionales (HU-023): un estudiante que no figura en la lista de un examen
 * (convalidación, inscripción tardía aprobada) se agrega SOLO a la lista de ese examen.
 *
 * No toca el padrón del grupo (grupo_estudiante) ni la nómina: ni la HU-020 ni la HU-021 lo ven, y
 * los demás exámenes del mismo grupo quedan intactos. La lista de esperados de un examen se deriva
 * del padrón (ExamParticipantService::expected()), por eso un estudiante «ya está en el examen»
 * cuando esa consulta lo devuelve.
 *
 * PENDIENTE (decisión 1 con el líder): dónde se guarda el registro. Ver persist().
 */
class ExceptionalStudentService
{
    /** Estados de examen que se ofrecen en el desplegable (con su aviso si no admiten registro). */
    private const OFFERED_STATES = [Exam::PROGRAMADO, Exam::EN_CURSO];

    /** Estados en los que el examen admite un estudiante excepcional (decisión 2 con el líder). */
    private const REGISTRABLE_STATES = [Exam::PROGRAMADO];

    private ExamParticipantService $participants;

    private AuditLogService $auditLog;

    public function __construct(ExamParticipantService $participants, AuditLogService $auditLog)
    {
        $this->participants = $participants;
        $this->auditLog = $auditLog;
    }

    /**
     * Exámenes propios del docente que se pueden elegir, del más próximo al más lejano, con el
     * aviso de si hoy admiten estudiantes excepcionales.
     *
     * @return array<int, object>
     */
    public function teacherExams(string $teacherId): array
    {
        return Exam::query()
            ->where('id_usuario_docente', $teacherId)
            ->whereIn('estado', self::OFFERED_STATES)
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
            ->orderBy('id_examen')
            ->get()
            ->map(function (Exam $exam): object {
                return (object) [
                    'id_examen' => (int) $exam->id_examen,
                    'nombre_examen' => (string) $exam->nombre_examen,
                    'fecha' => $exam->fecha->format('Y-m-d'),
                    'hora_inicio' => substr((string) $exam->hora_inicio, 0, 5),
                    'estado' => (string) $exam->estado,
                    'permitido' => $this->admits($exam),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Busca al estudiante por código SIS, en la misma tabla que usa la carga de nómina.
     * Devuelve null si no hay un estudiante activo con ese código.
     *
     * @throws AuthorizationException si el examen no es del docente
     */
    public function search(int $examId, string $teacherId, string $sisCode): ?object
    {
        $exam = $this->ownedExam($examId, $teacherId);

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
     * examen bloqueado, para que dos registros simultáneos no se pisen.
     *
     * @throws AuthorizationException si el examen no es del docente
     * @throws ExamStateException si el examen ya no admite estudiantes excepcionales
     * @throws ValidationException si el estudiante no existe o ya está en la lista del examen
     */
    public function register(int $examId, string $teacherId, int $studentId, string $reason): object
    {
        $this->ownedExam($examId, $teacherId);

        return DB::transaction(function () use ($examId, $teacherId, $studentId, $reason): object {
            DB::table('examen')
                ->where('id_examen', $examId)
                ->lockForUpdate()
                ->value('id_examen');

            // El estado se lee de nuevo con el examen bloqueado: pudo cambiar desde la comprobación.
            $exam = Exam::query()->findOrFail($examId);

            if (! $this->admits($exam)) {
                throw new ExamStateException('El examen ya no admite estudiantes excepcionales.');
            }

            $student = Student::query()
                ->where('estado', RecordStatus::ACTIVE)
                ->find($studentId);

            if ($student === null) {
                throw ValidationException::withMessages([
                    'id_estudiante' => ['No se encontró a ningún estudiante con este código'],
                ]);
            }

            if ($this->isExpected($examId, $studentId)) {
                throw ValidationException::withMessages([
                    'id_estudiante' => ['Ya figura en la lista de estudiantes de este examen'],
                ]);
            }

            $motivo = trim($reason);

            $this->persist($exam, $student, $motivo, $teacherId);

            return (object) [
                'id_examen' => $examId,
                'id_estudiante' => $studentId,
                'cod_sis' => (string) $student->cod_sis,
                'nombre_completo' => $student->nombre_completo,
                'carrera' => $this->latestCareer($studentId),
                'motivo_excepcional' => $motivo,
                'fecha_registro' => now()->toIso8601String(),
            ];
        });
    }

    /**
     * PENDIENTE (decisión 1 con el líder): guardar al estudiante en la lista de ESTE examen, sin
     * escribir en grupo_estudiante, y registrar la bitácora REGISTRAR_ESTUDIANTE_EXCEPCIONAL con
     * id_examen, id_estudiante, motivo y el docente.
     *
     * Se completa cuando se elija el almacenamiento (examen_estudiante con 'CASO ESPECIAL',
     * tabla nueva por examen o mixta). Se llama dentro de la transacción de register(), con el
     * examen bloqueado.
     */
    private function persist(Exam $exam, Student $student, string $reason, string $teacherId): void
    {
        throw new LogicException(
            'Falta definir dónde se guarda el estudiante excepcional (decisión 1 de la HU-023).'
        );
    }

    /** El examen debe ser del docente: uno inexistente responde 404 y uno ajeno, 403. */
    private function ownedExam(int $examId, string $teacherId): Exam
    {
        $exam = Exam::query()->findOrFail($examId);

        if (strtolower((string) $exam->id_usuario_docente) !== strtolower($teacherId)) {
            throw new AuthorizationException('No tiene acceso a este examen.');
        }

        return $exam;
    }

    private function admits(Exam $exam): bool
    {
        return in_array($exam->estado, self::REGISTRABLE_STATES, true);
    }

    /** Si el estudiante ya figura en la lista de esperados del examen (derivada del padrón). */
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
