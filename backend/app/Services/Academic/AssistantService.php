<?php

namespace App\Services\Academic;

use App\Models\Exam;
use App\Models\Group;
use App\Models\Role;
use App\Models\User;
use App\Services\Exams\ExamParticipantService;
use App\Services\Security\AuditLogService;
use App\Support\RecordStatus;
use App\Support\SisCode;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Habilitación de auxiliares (HU-08).
 *
 * Un auxiliar es siempre un usuario existente con rol Auxiliar. El docente
 * nunca lo crea: solo lo busca, lo incorpora a sus grupos y lo habilita para
 * un examen. Quitar a un auxiliar de un grupo o examen no afecta a los grupos
 * o exámenes de otros docentes que también lo tengan.
 *
 * El docente que actúa llega siempre como argumento desde el Controller. La
 * verificación de pertenencia (¿este grupo/examen es del docente?) se hace
 * aquí adentro, siguiendo el patrón de StudentRosterGroupAccess. No se usa
 * Policy porque el modelo Group ya tiene GroupPolicy registrada y Eloquent no
 * permite mapear dos Policies al mismo modelo.
 */
class AssistantService
{
    private const ACTION_ADD_TO_GROUP = 'ANADIR_AUXILIAR_GRUPO';
    private const ACTION_ENABLE_FOR_EXAM = 'HABILITAR_AUXILIAR_EXAMEN';
    private const ACTION_REMOVE_FROM_GROUP = 'QUITAR_AUXILIAR_GRUPO';
    private const ACTION_REMOVE_FROM_EXAM = 'QUITAR_AUXILIAR_EXAMEN';

    private SubjectCatalogService $subjectCatalog;
    private AuditLogService $auditLog;
    private ExamParticipantService $participants;

    public function __construct(
        SubjectCatalogService $subjectCatalog,
        AuditLogService $auditLog,
        ExamParticipantService $participants
    ) {
        $this->subjectCatalog = $subjectCatalog;
        $this->auditLog = $auditLog;
        $this->participants = $participants;
    }

    /**
     * Auxiliares con sus grupos del período activo, sus exámenes habilitados
     * y los exámenes donde todavía pueden habilitarse.
     *
     * La cantidad de consultas no depende de cuántos auxiliares haya: todo se
     * trae en bloque y se reparte en memoria. Solo crece con los exámenes
     * programados del docente, porque los estudiantes esperados se consultan
     * por examen con ExamParticipantService.
     *
     * @return array<int, array>
     */
    public function listForTeacher(string $teacherId): array
    {
        $periodId = $this->subjectCatalog->activePeriodId();

        $assistants = User::query()
            ->whereHas('assistantGroups', function ($query) use ($teacherId, $periodId) {
                $this->constrainToActiveGroups($query, $teacherId, $periodId);
            })
            ->with(['assistantGroups' => function ($query) use ($teacherId, $periodId) {
                $this->constrainToActiveGroups($query, $teacherId, $periodId);
                $query->with('subject');
            }])
            ->orderBy('apellido_paterno')
            ->orderBy('nombre')
            ->get();

        if ($assistants->isEmpty()) {
            return [];
        }

        $assistantIds = $assistants->pluck('id_usuario')->all();
        $groupIds = $assistants->flatMap(fn (User $user) => $user->assistantGroups->pluck('id_grupo'))
            ->unique()
            ->values()
            ->all();

        $programmed = $this->programmedExamsByGroup($teacherId, $groupIds);
        $enabled = $this->enabledExamsByAssistant($teacherId, $assistantIds);
        $alreadyEnabled = $this->enabledExamIdsByAssistant($assistantIds);
        $studentCodes = $this->studentCodesByExam(
            $programmed->flatten(1)->pluck('id_examen')->unique()->all(),
            $assistants->pluck('cod_sis')->map(fn ($code) => SisCode::normalize((string) $code))->all()
        );

        return $assistants->map(function (User $user) use ($programmed, $enabled, $alreadyEnabled, $studentCodes) {
            $userGroupIds = $user->assistantGroups->pluck('id_grupo')->all();
            $sis = SisCode::normalize((string) $user->cod_sis);

            $groups = $user->assistantGroups->map(function (Group $group) use ($programmed) {
                $exam = $programmed->get($group->id_grupo)?->first();

                return [
                    'id_grupo' => (int) $group->id_grupo,
                    'label' => $this->groupLabel($group),
                    'tiene_examen_programado' => $exam !== null,
                    'examen_programado' => $exam ? [
                        'nombre_examen' => $exam->nombre_examen,
                        'fecha' => $exam->fecha,
                    ] : null,
                ];
            })->values()->all();

            $available = collect($userGroupIds)
                ->flatMap(fn ($groupId) => $programmed->get($groupId, collect()))
                ->unique('id_examen')
                ->reject(fn ($exam) => in_array((int) $exam->id_examen, $alreadyEnabled->get($user->id_usuario, []), true))
                ->reject(fn ($exam) => in_array($sis, $studentCodes[(int) $exam->id_examen] ?? [], true))
                ->sortBy('fecha')
                ->map(fn ($exam) => $this->examSummary($exam))
                ->values()
                ->all();

            return [
                'id_usuario' => (string) $user->id_usuario,
                'nombre' => $user->nombre,
                'apellido_paterno' => $user->apellido_paterno,
                'apellido_materno' => $user->apellido_materno,
                'nombre_completo' => $user->nombre_completo,
                'cod_sis' => $user->cod_sis,
                'correo' => $user->correo,
                'grupos' => $groups,
                'examenes' => $enabled->get($user->id_usuario, collect())->map(fn ($exam) => $this->examSummary($exam))->all(),
                'examenes_disponibles' => $available,
            ];
        })->all();
    }

    /**
     * Auxiliares incorporados (ACTIVOS) a un grupo y los exámenes de ese grupo donde están
     * habilitados. Solo lectura: añadir y quitar siguen en sus propios métodos.
     *
     * Son dos consultas sin importar cuántos auxiliares haya. Los exámenes cancelados no se listan.
     *
     * @return array<int, array>
     */
    public function listForGroup(Group $group): array
    {
        $assistants = DB::table('grupo_auxiliar')
            ->join('usuario', 'usuario.id_usuario', '=', 'grupo_auxiliar.id_usuario')
            ->where('grupo_auxiliar.id_grupo', $group->id_grupo)
            ->where('grupo_auxiliar.estado', RecordStatus::ACTIVE)
            ->orderBy('usuario.apellido_paterno')
            ->orderBy('usuario.nombre')
            ->get([
                'usuario.id_usuario',
                'usuario.nombre',
                'usuario.apellido_paterno',
                'usuario.apellido_materno',
                'usuario.cod_sis',
                'usuario.correo',
                'grupo_auxiliar.fecha_incorporacion',
            ]);

        if ($assistants->isEmpty()) {
            return [];
        }

        $exams = DB::table('examen_auxiliar')
            ->join('examen', 'examen.id_examen', '=', 'examen_auxiliar.id_examen')
            ->join('grupo_examen', 'grupo_examen.id_examen', '=', 'examen.id_examen')
            ->where('grupo_examen.id_grupo', $group->id_grupo)
            ->where('examen.estado', '<>', Exam::CANCELADO)
            ->whereIn('examen_auxiliar.id_usuario', $assistants->pluck('id_usuario')->all())
            ->orderBy('examen.fecha')
            ->orderBy('examen.id_examen')
            ->get([
                'examen_auxiliar.id_usuario',
                'examen.id_examen',
                'examen.nombre_examen',
                'examen.fecha',
                'examen.estado',
            ])
            ->groupBy('id_usuario');

        return $assistants->map(function ($assistant) use ($exams) {
            return [
                'id_usuario' => (string) $assistant->id_usuario,
                'nombre_completo' => trim(implode(' ', array_filter([
                    $assistant->nombre,
                    $assistant->apellido_paterno,
                    $assistant->apellido_materno,
                ]))),
                'cod_sis' => $assistant->cod_sis,
                'correo' => $assistant->correo,
                'fecha_incorporacion' => $assistant->fecha_incorporacion,
                'examenes' => $exams->get($assistant->id_usuario, collect())
                    ->map(fn ($exam) => [
                        'id_examen' => (int) $exam->id_examen,
                        'nombre_examen' => $exam->nombre_examen,
                        'fecha' => $exam->fecha,
                        'estado' => $exam->estado,
                    ])
                    ->values()
                    ->all(),
            ];
        })->values()->all();
    }

    /**
     * Grupos del docente en el período activo.
     *
     * Se usa en el frontend para poblar los selectores de "Asignar auxiliar"
     * y "Mover de grupo". Devuelve solo id y label ya formateado.
     *
     * @return SupportCollection<int, object>
     */
    public function listTeacherGroups(string $teacherId): SupportCollection
    {
        return Group::query()
            ->where('id_usuario_docente', $teacherId)
            ->where('id_periodo', $this->subjectCatalog->activePeriodId())
            ->where('estado', RecordStatus::ACTIVE)
            ->with('subject')
            ->orderBy('id_materia')
            ->orderBy('num_grupo')
            ->get()
            ->map(function (Group $group) {
                return (object) [
                    'id_grupo' => (int) $group->id_grupo,
                    'num_grupo' => (string) $group->num_grupo,
                    'label' => $this->groupLabel($group),
                ];
            });
    }

    /**
     * Búsqueda de auxiliares registrados por SIS o nombre.
     *
     * Solo usuarios ACTIVOS con rol Auxiliar vigente (fecha_fin IS NULL).
     *
     * @return Collection<int, User>
     */
    public function search(string $criteria): Collection
    {
        $criteria = trim($criteria);

        if ($criteria === '') {
            return new Collection();
        }

        $pattern = '%' . $criteria . '%';

        return User::query()
            ->where('usuario.estado', RecordStatus::ACTIVE)
            ->join('usuario_rol', 'usuario_rol.id_usuario', '=', 'usuario.id_usuario')
            ->join('rol', 'rol.id_rol', '=', 'usuario_rol.id_rol')
            ->whereNull('usuario_rol.fecha_fin')
            ->where('rol.nombre_rol', Role::AUXILIAR)
            ->where(function ($q) use ($pattern) {
                $q->where('usuario.cod_sis', 'ILIKE', $pattern)
                ->orWhere('usuario.nombre', 'ILIKE', $pattern)
                ->orWhere('usuario.apellido_paterno', 'ILIKE', $pattern)
                ->orWhere('usuario.apellido_materno', 'ILIKE', $pattern)
                ->orWhereRaw(
                    "CONCAT(usuario.nombre, ' ', usuario.apellido_paterno, ' ', COALESCE(usuario.apellido_materno, '')) ILIKE ?",
                    [$pattern]
                );
            })
            ->select('usuario.*')
            ->distinct()
            ->orderBy('usuario.apellido_paterno')
            ->orderBy('usuario.nombre')
            ->limit(20)
            ->get();
    }

    /**
     * Incorpora un auxiliar a un grupo del docente.
     *
     * Reglas:
     *  - El grupo debe ser del docente.
     *  - El usuario debe tener rol Auxiliar vigente.
     *  - Si ya estaba ACTIVO: rechazar.
     *  - Si estaba INACTIVO: reactivar.
     */
    public function addToGroup(int $groupId, string $assistantId, string $teacherId): void
    {
        $this->findOwnGroupOrFail($groupId, $teacherId);
        $this->findAssistantOrFail($assistantId);

        $existing = DB::table('grupo_auxiliar')
            ->where('id_grupo', $groupId)
            ->where('id_usuario', $assistantId)
            ->first();

        if ($existing !== null && $existing->estado === RecordStatus::ACTIVE) {
            throw ValidationException::withMessages([
                'id_usuario' => ['El auxiliar ya está incorporado a este grupo.'],
            ]);
        }

        DB::transaction(function () use ($existing, $groupId, $assistantId, $teacherId) {
            if ($existing !== null) {
                DB::table('grupo_auxiliar')
                    ->where('id_grupo', $groupId)
                    ->where('id_usuario', $assistantId)
                    ->update(['estado' => RecordStatus::ACTIVE]);
            } else {
                DB::table('grupo_auxiliar')->insert([
                    'id_grupo' => $groupId,
                    'id_usuario' => $assistantId,
                    'fecha_incorporacion' => now()->toDateString(),
                    'estado' => RecordStatus::ACTIVE,
                ]);
            }

            $this->auditLog->registrar(
                self::ACTION_ADD_TO_GROUP,
                'grupo_auxiliar',
                null,
                [
                    'id_grupo' => $groupId,
                    'id_usuario' => $assistantId,
                    'estado' => RecordStatus::ACTIVE,
                ],
                $teacherId
            );
        });
    }

    /**
     * Incorpora un auxiliar a varios grupos a la vez.
     *
     * Es atómico: o se asigna a todos o a ninguno. Los grupos donde ya está
     * ACTIVO se ignoran silenciosamente (idempotente). Los grupos donde estaba
     * INACTIVO se reactivan.
     *
     * @param array<int, int> $groupIds
     */
    public function addToGroups(string $assistantId, array $groupIds, string $teacherId): void
    {
        $this->findAssistantOrFail($assistantId);

        $groupIds = array_values(array_unique(array_map('intval', $groupIds)));

        if ($groupIds === []) {
            throw ValidationException::withMessages([
                'grupos' => ['Debe seleccionar al menos un grupo.'],
            ]);
        }

        $groups = Group::query()->whereIn('id_grupo', $groupIds)->get();

        if ($groups->count() !== count($groupIds)) {
            throw ValidationException::withMessages([
                'grupos' => ['Alguno de los grupos seleccionados no existe.'],
            ]);
        }

        if ($groups->contains(fn (Group $group) => (string) $group->id_usuario_docente !== $teacherId)) {
            throw new AuthorizationException('Alguno de los grupos no pertenece al docente actual.');
        }

        if ($groups->contains(fn (Group $group) => $group->estado !== RecordStatus::ACTIVE)) {
            throw ValidationException::withMessages([
                'grupos' => ['Alguno de los grupos seleccionados no está activo.'],
            ]);
        }

        DB::transaction(function () use ($assistantId, $groupIds, $teacherId) {
            $existing = DB::table('grupo_auxiliar')
                ->where('id_usuario', $assistantId)
                ->whereIn('id_grupo', $groupIds)
                ->get(['id_grupo', 'estado'])
                ->keyBy('id_grupo');

            $toInsert = [];
            $toReactivate = [];

            foreach ($groupIds as $groupId) {
                if (!$existing->has($groupId)) {
                    $toInsert[] = [
                        'id_grupo' => $groupId,
                        'id_usuario' => $assistantId,
                        'fecha_incorporacion' => now()->toDateString(),
                        'estado' => RecordStatus::ACTIVE,
                    ];
                } elseif ($existing[$groupId]->estado === RecordStatus::INACTIVE) {
                    $toReactivate[] = $groupId;
                }
                // Si ya está ACTIVO, se ignora.
            }

            if ($toInsert !== []) {
                DB::table('grupo_auxiliar')->insert($toInsert);
            }

            if ($toReactivate !== []) {
                DB::table('grupo_auxiliar')
                    ->where('id_usuario', $assistantId)
                    ->whereIn('id_grupo', $toReactivate)
                    ->update(['estado' => RecordStatus::ACTIVE]);
            }

            $this->auditLog->registrar(
                self::ACTION_ADD_TO_GROUP,
                'grupo_auxiliar',
                null,
                [
                    'id_usuario' => $assistantId,
                    'id_grupo' => $groupIds,
                    'estado' => RecordStatus::ACTIVE,
                ],
                $teacherId
            );
        });
    }

    /**
     * Habilita un auxiliar para un examen.
     *
     * Reglas:
     *  - El examen debe ser del docente.
     *  - El examen debe estar PROGRAMADO: desde que se abre el control de
     *    ingreso, las habilitaciones quedan fijas.
     *  - El auxiliar debe pertenecer a algún grupo vinculado al examen.
     *  - El auxiliar no debe estar entre los estudiantes esperados del examen.
     *
     * El examen se bloquea con lockForUpdate para que no cambie de estado
     * entre la verificación y el insert.
     */
    public function enableForExam(int $examId, string $assistantId, string $teacherId): void
    {
        $assistant = $this->findAssistantOrFail($assistantId);

        DB::transaction(function () use ($examId, $assistantId, $assistant, $teacherId) {
            $exam = $this->lockOwnExamOrFail($examId, $teacherId);

            if ($exam->estado !== Exam::PROGRAMADO) {
                throw ValidationException::withMessages([
                    'id_examen' => [
                        'Solo se pueden habilitar auxiliares mientras el examen está PROGRAMADO.',
                    ],
                ]);
            }

            $belongsToExam = DB::table('grupo_auxiliar')
                ->join('grupo_examen', 'grupo_examen.id_grupo', '=', 'grupo_auxiliar.id_grupo')
                ->where('grupo_examen.id_examen', $examId)
                ->where('grupo_auxiliar.id_usuario', $assistantId)
                ->where('grupo_auxiliar.estado', RecordStatus::ACTIVE)
                ->exists();

            if (!$belongsToExam) {
                throw ValidationException::withMessages([
                    'id_usuario' => [
                        'El auxiliar no pertenece a ningún grupo vinculado a este examen.',
                    ],
                ]);
            }

            // Los estudiantes se derivan de la nómina: examen_estudiante solo existe desde un ingreso real.
            $isExpectedStudent = $this->participants->expected($examId)
                ->where('estudiante.cod_sis', SisCode::normalize((string) $assistant->cod_sis))
                ->exists();

            if ($isExpectedStudent) {
                throw ValidationException::withMessages([
                    'id_usuario' => [
                        'Esta persona está registrada como estudiante en este examen. '
                        . 'No puede habilitarse como auxiliar.',
                    ],
                ]);
            }

            $alreadyEnabled = DB::table('examen_auxiliar')
                ->where('id_examen', $examId)
                ->where('id_usuario', $assistantId)
                ->exists();

            if ($alreadyEnabled) {
                throw ValidationException::withMessages([
                    'id_usuario' => ['El auxiliar ya está habilitado para este examen.'],
                ]);
            }

            DB::table('examen_auxiliar')->insert([
                'id_examen' => $exam->id_examen,
                'id_usuario' => $assistantId,
                'id_usuario_docente_habilita' => $teacherId,
                'fecha_habilitacion' => now(),
                'id_ambiente' => null,
            ]);

            $this->auditLog->registrar(
                self::ACTION_ENABLE_FOR_EXAM,
                'examen_auxiliar',
                null,
                [
                    'id_examen' => $exam->id_examen,
                    'id_usuario' => $assistantId,
                ],
                $teacherId
            );
        });
    }

    /**
     * Quita un auxiliar de un grupo.
     *
     * grupo_auxiliar tiene estado: se marca INACTIVO, no se borra. No afecta
     * a la misma persona en los grupos de otros docentes.
     *
     * Si el grupo era el último vínculo del auxiliar con un examen PROGRAMADO
     * del docente, también se le deshabilita de ese examen en la misma
     * transacción. Si alguno de esos exámenes ya está EN_INGRESO o EN_CURSO,
     * la habilitación está fija y la operación se rechaza.
     */
    public function removeFromGroup(int $groupId, string $assistantId, string $teacherId): void
    {
        $this->findOwnGroupOrFail($groupId, $teacherId);
        $this->findAssistantOrFail($assistantId);

        $existing = DB::table('grupo_auxiliar')
            ->where('id_grupo', $groupId)
            ->where('id_usuario', $assistantId)
            ->first();

        if ($existing === null || $existing->estado !== RecordStatus::ACTIVE) {
            throw new ModelNotFoundException(
                'El auxiliar no está incorporado a este grupo.'
            );
        }

        DB::transaction(function () use ($groupId, $assistantId, $teacherId) {
            $orphaned = $this->examsLeftWithoutGroupLink($groupId, $assistantId, $teacherId);

            $started = $orphaned->filter(fn (Exam $exam) => in_array(
                $exam->estado,
                [Exam::EN_INGRESO, Exam::EN_CURSO],
                true
            ));

            if ($started->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'id_grupo' => [
                        'No se puede quitar al auxiliar de este grupo porque está habilitado en un examen '
                        . 'que ya inició el control de ingreso: '
                        . $started->pluck('nombre_examen')->implode(', ') . '.',
                    ],
                ]);
            }

            DB::table('grupo_auxiliar')
                ->where('id_grupo', $groupId)
                ->where('id_usuario', $assistantId)
                ->update(['estado' => RecordStatus::INACTIVE]);

            $this->auditLog->registrar(
                self::ACTION_REMOVE_FROM_GROUP,
                'grupo_auxiliar',
                ['id_grupo' => $groupId, 'id_usuario' => $assistantId, 'estado' => RecordStatus::ACTIVE],
                ['id_grupo' => $groupId, 'id_usuario' => $assistantId, 'estado' => RecordStatus::INACTIVE],
                $teacherId
            );

            foreach ($orphaned->where('estado', Exam::PROGRAMADO) as $exam) {
                $this->deleteExamEnablement((int) $exam->id_examen, $assistantId, $teacherId);
            }
        });
    }

    /**
     * Quita un auxiliar de un examen.
     *
     * examen_auxiliar NO tiene estado: se borra la fila.
     *
     * Reglas:
     *  - El examen debe ser del docente.
     *  - El examen debe estar PROGRAMADO: desde que se abre el control de
     *    ingreso, las habilitaciones quedan fijas.
     */
    public function removeFromExam(int $examId, string $assistantId, string $teacherId): void
    {
        $this->findAssistantOrFail($assistantId);

        DB::transaction(function () use ($examId, $assistantId, $teacherId) {
            $exam = $this->lockOwnExamOrFail($examId, $teacherId);

            if ($exam->estado !== Exam::PROGRAMADO) {
                throw ValidationException::withMessages([
                    'id_examen' => [
                        'Solo se pueden modificar los auxiliares mientras el examen está PROGRAMADO.',
                    ],
                ]);
            }

            $enabled = DB::table('examen_auxiliar')
                ->where('id_examen', $examId)
                ->where('id_usuario', $assistantId)
                ->exists();

            if (!$enabled) {
                throw new ModelNotFoundException(
                    'El auxiliar no está habilitado para este examen.'
                );
            }

            $this->deleteExamEnablement($examId, $assistantId, $teacherId);
        });
    }

    /* ----------------------------------------------------------------------
     * Helpers privados
     * -------------------------------------------------------------------- */

    private function deleteExamEnablement(int $examId, string $assistantId, string $teacherId): void
    {
        DB::table('examen_auxiliar')
            ->where('id_examen', $examId)
            ->where('id_usuario', $assistantId)
            ->delete();

        $this->auditLog->registrar(
            self::ACTION_REMOVE_FROM_EXAM,
            'examen_auxiliar',
            ['id_examen' => $examId, 'id_usuario' => $assistantId],
            null,
            $teacherId
        );
    }

    /**
     * Exámenes del docente donde el auxiliar está habilitado y el grupo indicado era su
     * único vínculo activo. Quedan bloqueados hasta cerrar la transacción. Solo cuentan los
     * que aún pueden cambiar: PROGRAMADO, o EN_INGRESO / EN_CURSO (que bloquean la operación).
     *
     * @return Collection<int, Exam>
     */
    private function examsLeftWithoutGroupLink(int $groupId, string $assistantId, string $teacherId): Collection
    {
        $examIds = DB::table('examen_auxiliar')
            ->join('examen', 'examen.id_examen', '=', 'examen_auxiliar.id_examen')
            ->join('grupo_examen', 'grupo_examen.id_examen', '=', 'examen.id_examen')
            ->where('examen_auxiliar.id_usuario', $assistantId)
            ->where('examen.id_usuario_docente', $teacherId)
            ->where('grupo_examen.id_grupo', $groupId)
            ->whereNotExists(function ($query) use ($groupId, $assistantId) {
                $query->select(DB::raw(1))
                    ->from('grupo_examen as otro_vinculo')
                    ->join('grupo_auxiliar', 'grupo_auxiliar.id_grupo', '=', 'otro_vinculo.id_grupo')
                    ->whereColumn('otro_vinculo.id_examen', 'examen.id_examen')
                    ->where('otro_vinculo.id_grupo', '!=', $groupId)
                    ->where('grupo_auxiliar.id_usuario', $assistantId)
                    ->where('grupo_auxiliar.estado', RecordStatus::ACTIVE);
            })
            ->pluck('examen.id_examen')
            ->all();

        if ($examIds === []) {
            return new Collection();
        }

        return Exam::query()
            ->whereIn('id_examen', $examIds)
            ->whereIn('estado', [Exam::PROGRAMADO, Exam::EN_INGRESO, Exam::EN_CURSO])
            ->lockForUpdate()
            ->get();
    }

    /** Aplica el filtro de "grupo activo del docente en el período" a la relación de grupos del auxiliar. */
    private function constrainToActiveGroups($query, string $teacherId, int $periodId): void
    {
        $query->where('grupo.id_usuario_docente', $teacherId)
            ->where('grupo.id_periodo', $periodId)
            ->where('grupo.estado', RecordStatus::ACTIVE)
            ->where('grupo_auxiliar.estado', RecordStatus::ACTIVE);
    }

    private function groupLabel(Group $group): string
    {
        return sprintf('%s · Grupo %s', $group->subject?->nombre ?? 'Materia', $group->num_grupo);
    }

    /** @return array{id_examen: int, nombre_examen: string, fecha: mixed} */
    private function examSummary(object $exam): array
    {
        return [
            'id_examen' => (int) $exam->id_examen,
            'nombre_examen' => $exam->nombre_examen,
            'fecha' => $exam->fecha,
        ];
    }

    private function findOwnGroupOrFail(int $groupId, string $teacherId): Group
    {
        $group = Group::query()->find($groupId);

        if ($group === null) {
            throw new ModelNotFoundException('No existe el grupo indicado.');
        }

        if ((string) $group->id_usuario_docente !== $teacherId) {
            throw new AuthorizationException('El grupo no pertenece al docente actual.');
        }

        return $group;
    }

    /** Bloquea el examen para que no cambie de estado durante la operación. */
    private function lockOwnExamOrFail(int $examId, string $teacherId): Exam
    {
        $exam = Exam::query()->lockForUpdate()->find($examId);

        if ($exam === null) {
            throw new ModelNotFoundException('No existe el examen indicado.');
        }

        if ((string) $exam->id_usuario_docente !== $teacherId) {
            throw new AuthorizationException('El examen no pertenece al docente actual.');
        }

        return $exam;
    }

    private function findAssistantOrFail(string $userId): User
    {
        $user = User::query()->find($userId);

        if ($user === null || $user->estado !== RecordStatus::ACTIVE) {
            throw new ModelNotFoundException('No existe el usuario indicado.');
        }

        $hasAssistantRole = $user->activeRoles()
            ->where('rol.nombre_rol', Role::AUXILIAR)
            ->exists();

        if (!$hasAssistantRole) {
            throw ValidationException::withMessages([
                'id_usuario' => ['El usuario indicado no tiene rol Auxiliar vigente.'],
            ]);
        }

        return $user;
    }

    /**
     * Exámenes PROGRAMADOS del docente vinculados a los grupos, por grupo y ordenados por fecha.
     *
     * @param array<int, int> $groupIds
     * @return SupportCollection<int, SupportCollection<int, object>>
     */
    private function programmedExamsByGroup(string $teacherId, array $groupIds): SupportCollection
    {
        return DB::table('examen')
            ->join('grupo_examen', 'grupo_examen.id_examen', '=', 'examen.id_examen')
            ->whereIn('grupo_examen.id_grupo', $groupIds)
            ->where('examen.id_usuario_docente', $teacherId)
            ->where('examen.estado', Exam::PROGRAMADO)
            ->orderBy('examen.fecha')
            ->orderBy('examen.id_examen')
            ->select('examen.id_examen', 'examen.nombre_examen', 'examen.fecha', 'grupo_examen.id_grupo')
            ->get()
            ->groupBy('id_grupo');
    }

    /**
     * Exámenes PROGRAMADOS del docente donde cada auxiliar ya está habilitado.
     *
     * @param array<int, string> $assistantIds
     * @return SupportCollection<string, SupportCollection<int, object>>
     */
    private function enabledExamsByAssistant(string $teacherId, array $assistantIds): SupportCollection
    {
        return DB::table('examen_auxiliar')
            ->join('examen', 'examen.id_examen', '=', 'examen_auxiliar.id_examen')
            ->whereIn('examen_auxiliar.id_usuario', $assistantIds)
            ->where('examen.id_usuario_docente', $teacherId)
            ->where('examen.estado', Exam::PROGRAMADO)
            ->orderBy('examen.fecha')
            ->orderBy('examen.id_examen')
            ->select('examen_auxiliar.id_usuario', 'examen.id_examen', 'examen.nombre_examen', 'examen.fecha')
            ->get()
            ->groupBy('id_usuario');
    }

    /**
     * Ids de todos los exámenes donde cada auxiliar está habilitado, de cualquier docente.
     *
     * @param array<int, string> $assistantIds
     * @return SupportCollection<string, array<int, int>>
     */
    private function enabledExamIdsByAssistant(array $assistantIds): SupportCollection
    {
        return DB::table('examen_auxiliar')
            ->whereIn('id_usuario', $assistantIds)
            ->get(['id_usuario', 'id_examen'])
            ->groupBy('id_usuario')
            ->map(fn (SupportCollection $rows) => $rows->pluck('id_examen')->map(fn ($id) => (int) $id)->all());
    }

    /**
     * Códigos SIS de los estudiantes esperados de cada examen, limitados a los códigos dados.
     *
     * @param array<int, int> $examIds
     * @param array<int, string> $sisCodes SIS ya normalizados
     * @return array<int, array<int, string>>
     */
    private function studentCodesByExam(array $examIds, array $sisCodes): array
    {
        $codes = [];

        foreach ($examIds as $examId) {
            $codes[(int) $examId] = $this->participants->expected((int) $examId)
                ->whereIn('estudiante.cod_sis', $sisCodes)
                ->get()
                ->map(fn ($student) => SisCode::normalize((string) $student->cod_sis))
                ->all();
        }

        return $codes;
    }
}
