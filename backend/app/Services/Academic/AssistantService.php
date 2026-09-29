<?php

namespace App\Services\Academic;

use App\Models\Exam;
use App\Models\Group;
use App\Models\Role;
use App\Models\User;
use App\Services\Security\AuditLogService;
use App\Support\RecordStatus;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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
 * La verificación de pertenencia (¿este grupo/examen es del docente?) se hace
 * aquí adentro, siguiendo el patrón de StudentRosterGroupAccess. No se usa
 * Policy porque el modelo Group ya tiene GroupPolicy registrada en
 * AuthServiceProvider y Eloquent no permite mapear dos Policies al mismo modelo.
 */
class AssistantService
{
    private SubjectCatalogService $subjectCatalog;
    private AuditLogService $auditLog;

    public function __construct(
        SubjectCatalogService $subjectCatalog,
        AuditLogService $auditLog
    ) {
        $this->subjectCatalog = $subjectCatalog;
        $this->auditLog = $auditLog;
    }

    /**
     * Auxiliares con sus grupos del período activo, sus exámenes habilitados
     * y si esos grupos tienen exámenes PROGRAMADOS.
     *
     * @return array<int, array>
     */
    public function listarMisAuxiliares(): array
    {
        $teacherId = $this->subjectCatalog->teacherId();
        $periodId = $this->subjectCatalog->activePeriodId();

        $auxiliares = User::query()
            ->whereHas('groupsAsAuxiliar', function ($q) use ($teacherId, $periodId) {
                $q->where('grupo.id_usuario_docente', $teacherId)
                ->where('grupo.id_periodo', $periodId)
                ->where('grupo.estado', RecordStatus::ACTIVE)
                ->where('grupo_auxiliar.estado', RecordStatus::ACTIVE);
            })
            ->with(['groupsAsAuxiliar' => function ($q) use ($teacherId, $periodId) {
                $q->where('grupo.id_usuario_docente', $teacherId)
                ->where('grupo.id_periodo', $periodId)
                ->where('grupo.estado', RecordStatus::ACTIVE)
                ->where('grupo_auxiliar.estado', RecordStatus::ACTIVE)
                ->with('subject');
            }])
            ->orderBy('apellido_paterno')
            ->orderBy('nombre')
            ->get();

        return $auxiliares->map(function (User $user) use ($teacherId) {
            $grupos = $user->groupsAsAuxiliar->map(function (Group $g) {
                $examen = DB::table('grupo_examen')
                    ->join('examen', 'examen.id_examen', '=', 'grupo_examen.id_examen')
                    ->where('grupo_examen.id_grupo', $g->id_grupo)
                    ->where('examen.estado', Exam::PROGRAMADO)
                    ->select('examen.nombre_examen', 'examen.fecha')
                    ->first();

                return [
                    'id_grupo' => (int) $g->id_grupo,
                    'label' => sprintf('%s · Grupo %s', $g->subject?->nombre ?? 'Materia', $g->num_grupo),
                    'tiene_examen_programado' => $examen !== null,
                    'examen_programado' => $examen ? [
                        'nombre_examen' => $examen->nombre_examen,
                        'fecha' => $examen->fecha,
                    ] : null,
                ];
            })->values()->all();

            $groupIds = $user->groupsAsAuxiliar->pluck('id_grupo')->all();

            return [
                'id_usuario' => (string) $user->id_usuario,
                'nombre' => $user->nombre,
                'apellido_paterno' => $user->apellido_paterno,
                'apellido_materno' => $user->apellido_materno,
                'nombre_completo' => $user->nombre_completo,
                'cod_sis' => $user->cod_sis,
                'correo' => $user->correo,
                'grupos' => $grupos,
                'examenes' => $this->examenesHabilitadosPara($user, $teacherId),
                'examenes_disponibles' => $this->examenesDisponiblesPara($user, $teacherId, $groupIds),
            ];
        })->all();
    }

    /**
     * Grupos del docente en el período activo.
     *
     * Se usa en el frontend para poblar los selectores de "Asignar auxiliar"
     * y "Mover de grupo". Devuelve solo id y label ya formateado.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    public function listarMisGrupos(): \Illuminate\Support\Collection
    {
        $teacherId = $this->subjectCatalog->teacherId();
        $periodId = $this->subjectCatalog->activePeriodId();

        return Group::query()
            ->where('id_usuario_docente', $teacherId)
            ->where('id_periodo', $periodId)
            ->where('estado', RecordStatus::ACTIVE)
            ->with('subject')
            ->orderBy('id_materia')
            ->orderBy('num_grupo')
            ->get()
            ->map(function (Group $group) {
                return (object) [
                    'id_grupo' => (int) $group->id_grupo,
                    'num_grupo' => (string) $group->num_grupo,
                    'label' => sprintf(
                        '%s · Grupo %s',
                        $group->subject?->nombre ?? 'Materia',
                        $group->num_grupo
                    ),
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
    public function buscar(string $criterio): Collection
    {
        $criterio = trim($criterio);

        if ($criterio === '') {
            return new Collection();
        }

        $patron = '%' . $criterio . '%';

        return User::query()
            ->where('usuario.estado', RecordStatus::ACTIVE)
            ->join('usuario_rol', 'usuario_rol.id_usuario', '=', 'usuario.id_usuario')
            ->join('rol', 'rol.id_rol', '=', 'usuario_rol.id_rol')
            ->whereNull('usuario_rol.fecha_fin')
            ->where('rol.nombre_rol', Role::AUXILIAR)
            ->where(function ($q) use ($patron) {
                $q->where('usuario.cod_sis', 'ILIKE', $patron)
                ->orWhere('usuario.nombre', 'ILIKE', $patron)
                ->orWhere('usuario.apellido_paterno', 'ILIKE', $patron)
                ->orWhere('usuario.apellido_materno', 'ILIKE', $patron)
                ->orWhereRaw(
                    "CONCAT(usuario.nombre, ' ', usuario.apellido_paterno, ' ', COALESCE(usuario.apellido_materno, '')) ILIKE ?",
                    [$patron]
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
    public function anadirAGrupo(int $idGrupo, string $idUsuarioAuxiliar): void
    {
        $this->findOwnGroupOrFail($idGrupo);
        $this->findAuxiliarOrFail($idUsuarioAuxiliar);

        $existing = DB::table('grupo_auxiliar')
            ->where('id_grupo', $idGrupo)
            ->where('id_usuario', $idUsuarioAuxiliar)
            ->first();

        if ($existing !== null && $existing->estado === RecordStatus::ACTIVE) {
            throw ValidationException::withMessages([
                'id_usuario' => ['El auxiliar ya está incorporado a este grupo.'],
            ]);
        }

        DB::transaction(function () use ($existing, $idGrupo, $idUsuarioAuxiliar) {
            if ($existing !== null) {
                DB::table('grupo_auxiliar')
                    ->where('id_grupo', $idGrupo)
                    ->where('id_usuario', $idUsuarioAuxiliar)
                    ->update(['estado' => RecordStatus::ACTIVE]);
            } else {
                DB::table('grupo_auxiliar')->insert([
                    'id_grupo' => $idGrupo,
                    'id_usuario' => $idUsuarioAuxiliar,
                    'fecha_incorporacion' => now()->toDateString(),
                    'estado' => RecordStatus::ACTIVE,
                ]);
            }

            $this->auditLog->registrar(
                operacion: 'añadir_auxiliar_grupo',
                tabla: 'grupo_auxiliar',
                antes: null,
                despues: [
                    'id_grupo' => $idGrupo,
                    'id_usuario' => $idUsuarioAuxiliar,
                    'estado' => RecordStatus::ACTIVE,
                ],
                idUsuario: $this->subjectCatalog->teacherId()
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
    public function anadirAVariosGrupos(string $idUsuarioAuxiliar, array $groupIds): void
    {
        $this->findAuxiliarOrFail($idUsuarioAuxiliar);

        if ($groupIds === []) {
            throw ValidationException::withMessages([
                'grupos' => ['Debe seleccionar al menos un grupo.'],
            ]);
        }

        $teacherId = $this->subjectCatalog->teacherId();

        // Verifica que TODOS los grupos sean del docente y estén activos.
        $ownedGroups = Group::query()
            ->whereIn('id_grupo', $groupIds)
            ->where('id_usuario_docente', $teacherId)
            ->where('estado', RecordStatus::ACTIVE)
            ->pluck('id_grupo')
            ->all();

        $missing = array_diff($groupIds, $ownedGroups);

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'grupos' => [
                    'Alguno de los grupos seleccionados no le pertenece o no está activo.',
                ],
            ]);
        }

        DB::transaction(function () use ($idUsuarioAuxiliar, $groupIds, $teacherId) {
            // Grupos donde ya está (activos o inactivos).
            $existing = DB::table('grupo_auxiliar')
                ->where('id_usuario', $idUsuarioAuxiliar)
                ->whereIn('id_grupo', $groupIds)
                ->get(['id_grupo', 'estado'])
                ->keyBy('id_grupo');

            $toInsert = [];
            $toReactivate = [];

            foreach ($groupIds as $groupId) {
                if (!$existing->has($groupId)) {
                    $toInsert[] = [
                        'id_grupo' => $groupId,
                        'id_usuario' => $idUsuarioAuxiliar,
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
                    ->where('id_usuario', $idUsuarioAuxiliar)
                    ->whereIn('id_grupo', $toReactivate)
                    ->update(['estado' => RecordStatus::ACTIVE]);
            }

            $this->auditLog->registrar(
                operacion: 'añadir_auxiliar_grupo',
                tabla: 'grupo_auxiliar',
                antes: null,
                despues: [
                    'id_usuario' => $idUsuarioAuxiliar,
                    'id_grupo' => $groupIds,
                    'estado' => RecordStatus::ACTIVE,
                ],
                idUsuario: $teacherId
            );
        });
    }

    /**
     * Habilita un auxiliar para un examen.
     *
     * Reglas:
     *  - El examen debe ser del docente.
     *  - El auxiliar debe pertenecer a algún grupo vinculado al examen.
     *  - El auxiliar no debe estar registrado como estudiante del mismo examen.
     */
    public function habilitarParaExamen(int $idExamen, string $idUsuarioAuxiliar): void
    {
        $exam = $this->findOwnExamOrFail($idExamen);
        $auxiliar = $this->findAuxiliarOrFail($idUsuarioAuxiliar);

        $perteneceAlExamen = DB::table('grupo_auxiliar')
            ->join('grupo_examen', 'grupo_examen.id_grupo', '=', 'grupo_auxiliar.id_grupo')
            ->where('grupo_examen.id_examen', $idExamen)
            ->where('grupo_auxiliar.id_usuario', $idUsuarioAuxiliar)
            ->where('grupo_auxiliar.estado', RecordStatus::ACTIVE)
            ->exists();

        if (!$perteneceAlExamen) {
            throw ValidationException::withMessages([
                'id_usuario' => [
                    'El auxiliar no pertenece a ningún grupo vinculado a este examen.',
                ],
            ]);
        }

        $esEstudianteDelExamen = DB::table('examen_estudiante')
            ->join('estudiante', 'estudiante.id_estudiante', '=', 'examen_estudiante.id_estudiante')
            ->where('examen_estudiante.id_examen', $idExamen)
            ->where('estudiante.cod_sis', $auxiliar->cod_sis)
            ->exists();

        if ($esEstudianteDelExamen) {
            throw ValidationException::withMessages([
                'id_usuario' => [
                    'Esta persona ya está registrada como estudiante en este examen. '
                    . 'No puede habilitarse como auxiliar.',
                ],
            ]);
        }

        $yaHabilitado = DB::table('examen_auxiliar')
            ->where('id_examen', $idExamen)
            ->where('id_usuario', $idUsuarioAuxiliar)
            ->exists();

        if ($yaHabilitado) {
            throw ValidationException::withMessages([
                'id_usuario' => ['El auxiliar ya está habilitado para este examen.'],
            ]);
        }

        DB::transaction(function () use ($exam, $idUsuarioAuxiliar) {
            DB::table('examen_auxiliar')->insert([
                'id_examen' => $exam->id_examen,
                'id_usuario' => $idUsuarioAuxiliar,
                'id_usuario_docente_habilita' => $this->subjectCatalog->teacherId(),
                'fecha_habilitacion' => now(),
                'id_ambiente' => null,
            ]);

            $this->auditLog->registrar(
                operacion: 'habilitar_auxiliar_examen',
                tabla: 'examen_auxiliar',
                antes: null,
                despues: [
                    'id_examen' => $exam->id_examen,
                    'id_usuario' => $idUsuarioAuxiliar,
                ],
                idUsuario: $this->subjectCatalog->teacherId()
            );
        });
    }

    /**
     * Quita un auxiliar de un grupo.
     *
     * grupo_auxiliar tiene estado: se marca INACTIVO, no se borra. No afecta
     * a la misma persona en los grupos de otros docentes.
     */
    public function quitarDeGrupo(int $idGrupo, string $idUsuarioAuxiliar): void
    {
        $this->findOwnGroupOrFail($idGrupo);
        $this->findAuxiliarOrFail($idUsuarioAuxiliar);

        $existing = DB::table('grupo_auxiliar')
            ->where('id_grupo', $idGrupo)
            ->where('id_usuario', $idUsuarioAuxiliar)
            ->first();

        if ($existing === null || $existing->estado !== RecordStatus::ACTIVE) {
            throw new ModelNotFoundException(
                'El auxiliar no está incorporado a este grupo.'
            );
        }

        DB::transaction(function () use ($idGrupo, $idUsuarioAuxiliar, $existing) {
            DB::table('grupo_auxiliar')
                ->where('id_grupo', $idGrupo)
                ->where('id_usuario', $idUsuarioAuxiliar)
                ->update(['estado' => RecordStatus::INACTIVE]);

            $this->auditLog->registrar(
                operacion: 'quitar_auxiliar_grupo',
                tabla: 'grupo_auxiliar',
                antes: ['estado' => $existing->estado],
                despues: ['estado' => RecordStatus::INACTIVE],
                idUsuario: $this->subjectCatalog->teacherId()
            );
        });
    }

    /**
     * Quita un auxiliar de un examen.
     *
     * examen_auxiliar NO tiene estado: se borra la fila.
     */
    public function quitarDeExamen(int $idExamen, string $idUsuarioAuxiliar): void
    {
        $this->findOwnExamOrFail($idExamen);
        $this->findAuxiliarOrFail($idUsuarioAuxiliar);

        $existing = DB::table('examen_auxiliar')
            ->where('id_examen', $idExamen)
            ->where('id_usuario', $idUsuarioAuxiliar)
            ->first();

        if ($existing === null) {
            throw new ModelNotFoundException(
                'El auxiliar no está habilitado para este examen.'
            );
        }

        DB::transaction(function () use ($idExamen, $idUsuarioAuxiliar) {
            DB::table('examen_auxiliar')
                ->where('id_examen', $idExamen)
                ->where('id_usuario', $idUsuarioAuxiliar)
                ->delete();

            $this->auditLog->registrar(
                operacion: 'quitar_auxiliar_examen',
                tabla: 'examen_auxiliar',
                antes: [
                    'id_examen' => $idExamen,
                    'id_usuario' => $idUsuarioAuxiliar,
                ],
                despues: null,
                idUsuario: $this->subjectCatalog->teacherId()
            );
        });
    }

    /* ----------------------------------------------------------------------
     * Helpers privados
     * -------------------------------------------------------------------- */

    private function findOwnGroupOrFail(int $idGrupo): Group
    {
        $group = Group::query()->find($idGrupo);

        if ($group === null) {
            throw new ModelNotFoundException('No existe el grupo indicado.');
        }

        if ((string) $group->id_usuario_docente !== $this->subjectCatalog->teacherId()) {
            throw new AuthorizationException('El grupo no pertenece al docente actual.');
        }

        return $group;
    }

    private function findOwnExamOrFail(int $idExamen): Exam
    {
        $exam = Exam::query()->find($idExamen);

        if ($exam === null) {
            throw new ModelNotFoundException('No existe el examen indicado.');
        }

        if ((string) $exam->id_usuario_docente !== $this->subjectCatalog->teacherId()) {
            throw new AuthorizationException('El examen no pertenece al docente actual.');
        }

        return $exam;
    }

    private function findAuxiliarOrFail(string $idUsuario): User
    {
        $user = User::query()->find($idUsuario);

        if ($user === null || $user->estado !== RecordStatus::ACTIVE) {
            throw new ModelNotFoundException('No existe el usuario indicado.');
        }

        $tieneRolAuxiliar = $user->activeRoles()
            ->where('rol.nombre_rol', Role::AUXILIAR)
            ->exists();

        if (!$tieneRolAuxiliar) {
            throw ValidationException::withMessages([
                'id_usuario' => ['El usuario indicado no tiene rol Auxiliar vigente.'],
            ]);
        }

        return $user;
    }

    /**
     * Exámenes en los que el auxiliar ya está habilitado (PROGRAMADOS).
     *
     * @return array<int, array>
     */
    private function examenesHabilitadosPara(User $user, string $teacherId): array
    {
        return DB::table('examen_auxiliar')
            ->join('examen', 'examen.id_examen', '=', 'examen_auxiliar.id_examen')
            ->where('examen_auxiliar.id_usuario', $user->id_usuario)
            ->where('examen.id_usuario_docente', $teacherId)
            ->where('examen.estado', Exam::PROGRAMADO)
            ->orderBy('examen.fecha')
            ->select('examen.id_examen', 'examen.nombre_examen', 'examen.fecha')
            ->get()
            ->map(fn ($e) => [
                'id_examen' => (int) $e->id_examen,
                'nombre_examen' => $e->nombre_examen,
                'fecha' => $e->fecha,
            ])
            ->all();
    }

    /**
     * Exámenes donde el auxiliar PUEDE ser habilitado.
     *
     * Filtros:
     *  - El examen es del docente y está PROGRAMADO.
     *  - Al menos un grupo del auxiliar está vinculado al examen.
     *  - El auxiliar no está ya habilitado en ese examen.
     *  - El auxiliar no es estudiante del mismo examen (por cod_sis).
     *
     * @param array<int, int> $groupIds
     * @return array<int, array>
     */
    private function examenesDisponiblesPara(
        User $user,
        string $teacherId,
        array $groupIds
    ): array {
        if ($groupIds === []) {
            return [];
        }

        $yaHabilitados = DB::table('examen_auxiliar')
            ->where('id_usuario', $user->id_usuario)
            ->pluck('id_examen')
            ->all();

        return DB::table('examen')
            ->join('grupo_examen', 'grupo_examen.id_examen', '=', 'examen.id_examen')
            ->whereIn('grupo_examen.id_grupo', $groupIds)
            ->where('examen.id_usuario_docente', $teacherId)
            ->where('examen.estado', Exam::PROGRAMADO)
            ->whereNotIn('examen.id_examen', $yaHabilitados)
            // Excluir exámenes donde el auxiliar es estudiante (mismo cod_sis)
            ->whereNotExists(function ($query) use ($user) {
                $query->select(DB::raw(1))
                    ->from('examen_estudiante')
                    ->join('estudiante', 'estudiante.id_estudiante', '=', 'examen_estudiante.id_estudiante')
                    ->whereColumn('examen_estudiante.id_examen', 'examen.id_examen')
                    ->where('estudiante.cod_sis', $user->cod_sis);
            })
            ->orderBy('examen.fecha')
            ->select('examen.id_examen', 'examen.nombre_examen', 'examen.fecha')
            ->distinct()
            ->get()
            ->map(fn ($e) => [
                'id_examen' => (int) $e->id_examen,
                'nombre_examen' => $e->nombre_examen,
                'fecha' => $e->fecha,
            ])
            ->all();
    }
}