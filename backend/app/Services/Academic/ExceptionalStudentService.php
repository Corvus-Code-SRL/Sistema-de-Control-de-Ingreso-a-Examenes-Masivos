<?php

namespace App\Services\Academic;

use App\Exceptions\Exams\ExamStateException;
use App\Models\Group;
use App\Models\Student;
use App\Services\Exams\ExamRosterLockService;
use App\Services\Security\AuditLogService;
use App\Support\RecordStatus;
use App\Support\SisCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Estudiantes excepcionales (HU-023): un estudiante que no figura en la nómina de un grupo
 * (convalidación, inscripción tardía aprobada) entra al grupo con la marca «Excepcional» y su motivo.
 *
 * Solo escribe su propia fila en grupo_estudiante: no recarga la nómina, y la carga de nómina (021)
 * solo inserta con ON CONFLICT DO NOTHING, así que nunca la borra. Con el examen PROGRAMADO el
 * estudiante pasa a ser esperado (los participantes se derivan de grupo_estudiante); con uno en
 * ingreso o en curso el registro se rechaza (409).
 */
class ExceptionalStudentService
{
    private StudentRosterGroupAccess $groupAccess;

    private ExamRosterLockService $rosterLock;

    private GroupCareerService $groupCareers;

    private AssistantService $assistants;

    private AuditLogService $auditLog;

    public function __construct(
        StudentRosterGroupAccess $groupAccess,
        ExamRosterLockService $rosterLock,
        GroupCareerService $groupCareers,
        AssistantService $assistants,
        AuditLogService $auditLog
    ) {
        $this->groupAccess = $groupAccess;
        $this->rosterLock = $rosterLock;
        $this->groupCareers = $groupCareers;
        $this->assistants = $assistants;
        $this->auditLog = $auditLog;
    }

    /**
     * Grupos del docente en el período vigente, con las carreras que atiende cada uno y si hoy están
     * bloqueados por un examen en ingreso o en curso.
     *
     * @return array<int, object>
     */
    public function teacherGroups(string $teacherId): array
    {
        $options = $this->assistants->listTeacherGroups($teacherId);

        if ($options->isEmpty()) {
            return [];
        }

        $models = Group::query()
            ->whereIn('id_grupo', $options->pluck('id_grupo')->all())
            ->get()
            ->keyBy('id_grupo');

        return $options->map(function ($option) use ($models): object {
            $group = $models->get($option->id_grupo);

            return (object) [
                'id_grupo' => (int) $option->id_grupo,
                'num_grupo' => (string) $option->num_grupo,
                'label' => (string) $option->label,
                'carreras' => $group === null ? [] : $this->groupCareers->careersOf($group),
                'bloqueado' => $this->rosterLock->isLocked((int) $option->id_grupo),
            ];
        })->values()->all();
    }

    /**
     * Busca al estudiante por código SIS, en la misma tabla que usa la carga de nómina.
     * Devuelve null si no hay un estudiante activo con ese código.
     */
    public function search(int $groupId, string $teacherId, string $sisCode): ?object
    {
        $group = $this->groupAccess->getOwnedActive($groupId, $teacherId);

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
            'ci' => $student->ci === null ? null : (string) $student->ci,
            'carrera' => $this->latestCareer((int) $student->id_estudiante),
            'ya_inscrito' => $this->isEnrolled((int) $group->id_grupo, (int) $student->id_estudiante),
        ];
    }

    /**
     * Inscribe al estudiante como excepcional. Una sola transacción con el grupo bloqueado, para que
     * dos registros o una carga de nómina simultáneos no se pisen.
     *
     * @throws ExamStateException si un examen del grupo está en ingreso o en curso
     * @throws ValidationException si ya está inscrito, no existe o la carrera no es del grupo
     */
    public function register(
        int $groupId,
        string $teacherId,
        int $studentId,
        string $reason,
        ?int $careerId
    ): object {
        $group = $this->groupAccess->getOwnedActive($groupId, $teacherId);

        return DB::transaction(function () use ($group, $teacherId, $studentId, $reason, $careerId): object {
            DB::table('grupo')
                ->where('id_grupo', $group->id_grupo)
                ->lockForUpdate()
                ->value('id_grupo');

            if ($this->rosterLock->isLocked((int) $group->id_grupo)) {
                throw new ExamStateException(
                    'No se puede registrar un estudiante excepcional mientras el grupo '
                    . 'tiene un examen en ingreso o en curso.'
                );
            }

            $student = Student::query()
                ->where('estado', RecordStatus::ACTIVE)
                ->find($studentId);

            if ($student === null) {
                throw ValidationException::withMessages([
                    'id_estudiante' => ['No se encontró a ningún estudiante con este código'],
                ]);
            }

            if ($this->isEnrolled((int) $group->id_grupo, $studentId)) {
                throw ValidationException::withMessages([
                    'id_estudiante' => ['Ya está inscrito en este grupo'],
                ]);
            }

            $career = $this->resolveCareer($group, $careerId);
            $motivo = trim($reason);
            $fecha = now()->toDateString();

            DB::table('grupo_estudiante')->insert([
                'id_grupo' => (int) $group->id_grupo,
                'id_estudiante' => $studentId,
                'fecha_inscripcion' => $fecha,
                'estado' => RecordStatus::ACTIVE,
                'id_carrera' => (int) $career['id_carrera'],
                'es_excepcional' => true,
                'motivo_excepcional' => $motivo,
            ]);

            $this->auditLog->registrar(
                'REGISTRAR_ESTUDIANTE_EXCEPCIONAL',
                'grupo_estudiante',
                null,
                [
                    'id_grupo' => (int) $group->id_grupo,
                    'id_estudiante' => $studentId,
                    'id_carrera' => (int) $career['id_carrera'],
                    'es_excepcional' => true,
                    'motivo_excepcional' => $motivo,
                ],
                $teacherId
            );

            return (object) [
                'id_grupo' => (int) $group->id_grupo,
                'id_estudiante' => $studentId,
                'cod_sis' => (string) $student->cod_sis,
                'nombre_completo' => $student->nombre_completo,
                'carrera' => [
                    'id_carrera' => (int) $career['id_carrera'],
                    'nombre' => (string) $career['nombre'],
                ],
                'es_excepcional' => true,
                'motivo_excepcional' => $motivo,
                'fecha_inscripcion' => $fecha,
            ];
        });
    }

    private function isEnrolled(int $groupId, int $studentId): bool
    {
        return DB::table('grupo_estudiante')
            ->where('id_grupo', $groupId)
            ->where('id_estudiante', $studentId)
            ->exists();
    }

    /**
     * Carrera de la inscripción más reciente del estudiante en otro grupo, o null si no tiene.
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

    /**
     * La carrera debe ser una de las que atiende el grupo; con una sola, se toma sin preguntar.
     *
     * @return array{id_carrera: int, nombre: string}
     */
    private function resolveCareer(Group $group, ?int $careerId): array
    {
        $careers = $this->groupCareers->careersOf($group);

        if ($careerId === null) {
            if (count($careers) === 1) {
                return $careers[0];
            }

            throw ValidationException::withMessages([
                'id_carrera' => ['Elija la carrera con la que el estudiante está en el grupo.'],
            ]);
        }

        foreach ($careers as $career) {
            if ((int) $career['id_carrera'] === $careerId) {
                return $career;
            }
        }

        throw ValidationException::withMessages([
            'id_carrera' => ['La carrera elegida no la atiende el grupo.'],
        ]);
    }
}
