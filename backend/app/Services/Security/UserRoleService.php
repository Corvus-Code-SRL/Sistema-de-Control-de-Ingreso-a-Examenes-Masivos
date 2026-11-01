<?php

namespace App\Services\Security;

use App\Exceptions\Security\RoleNotAllowedForAccountException;
use App\Exceptions\Security\SelfRoleAssignmentException;
use App\Models\Role;
use App\Models\User;
use App\Services\Academic\SubjectCatalogService;
use App\Support\RecordStatus;
use App\Support\SisCode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Asigna, modifica y consulta el rol de una cuenta.
 *
 * Una cuenta tiene un solo rol vigente, pero el historial no se borra: al
 * cambiar de rol se cierra el anterior con fecha_fin y se inserta el nuevo.
 */
class UserRoleService
{
    private AuditLogService $auditLog;

    private SubjectCatalogService $subjectCatalog;

    public function __construct(
        AuditLogService $auditLog,
        SubjectCatalogService $subjectCatalog
    ) {
        $this->auditLog = $auditLog;
        $this->subjectCatalog = $subjectCatalog;
    }

    /**
     * Cambia el rol de una cuenta. El de un docente invitado (código INV) no se cambia nunca: nace
     * con el rol Invitado al registrarse y ese es el único que tiene.
     */
    public function assignRole(User $user, int $roleId, string $actorId): Role
    {
        if ($actorId === $user->id_usuario) {
            throw new SelfRoleAssignmentException();
        }

        if (SisCode::isGuest(SisCode::normalize($user->cod_sis))) {
            throw RoleNotAllowedForAccountException::guestRoleLocked();
        }

        $newRole = Role::findOrFail($roleId);

        $this->assertRoleFitsAccount($user->cod_sis, $newRole->nombre_rol);

        return $this->changeRole($user, $newRole, $actorId);
    }

    /**
     * Rol con el que nace una cuenta recién registrada (HU-001). Es el único camino que asigna el rol
     * a una cuenta INV, porque assignRole() no permite cambiarlo.
     */
    public function assignInitialRole(User $user, Role $role, string $actorId): Role
    {
        $this->assertRoleFitsAccount($user->cod_sis, $role->nombre_rol);

        return $this->changeRole($user, $role, $actorId);
    }

    private function changeRole(User $user, Role $newRole, string $actorId): Role
    {
        $roleId = (int) $newRole->id_rol;

        return DB::transaction(function () use ($user, $roleId, $actorId, $newRole) {
            /*
             * Se bloquea la fila de la cuenta, no solo la de usuario_rol: cuando la
             * cuenta aún no tiene rol no hay fila que bloquear, y dos peticiones
             * simultáneas podrían insertar cada una su rol vigente.
             */
            User::whereKey($user->id_usuario)->lockForUpdate()->firstOrFail();

            $previousRole = $user->activeRoles()->lockForUpdate()->first();

            // Si el rol no cambia realmente no se registra nada.
            if ($previousRole && (int) $previousRole->id_rol === $roleId) {
                return $previousRole;
            }

            $changedAt = now()->startOfSecond();

            if ($previousRole) {
                DB::table('usuario_rol')
                    ->where('id_usuario', $user->id_usuario)
                    ->where('id_rol', $previousRole->id_rol)
                    ->whereNull('fecha_fin')
                    ->update(['fecha_fin' => $changedAt]);
            }

            $this->openRole($user, $roleId, $changedAt);

            // El estado de la cuenta no se toca: asignar rol no habilita una cuenta deshabilitada.

            $this->auditLog->registrar(
                $previousRole ? 'MODIFICAR' : 'ASIGNAR_ROL',
                'usuario_rol',
                $previousRole
                    ? [
                        'id_usuario' => $user->id_usuario,
                        'id_rol'     => $previousRole->id_rol,
                        'nombre_rol' => $previousRole->nombre_rol,
                    ]
                    : null,
                [
                    'id_usuario' => $user->id_usuario,
                    'id_rol'     => $newRole->id_rol,
                    'nombre_rol' => $newRole->nombre_rol,
                ],
                $actorId
            );

            return $newRole;
        });
    }

    /**
     * Abre la asignación del rol en $startedAt.
     *
     * La clave de usuario_rol incluye fecha_inicio con precisión de segundo: un cambio A→B→A dentro
     * del mismo segundo volvería a insertar (usuario, A, fecha_inicio) y chocaría con la fila de A
     * que acaba de cerrarse. Si esa fila ya existe se reabre en lugar de insertar otra.
     */
    private function openRole(User $user, int $roleId, Carbon $startedAt): void
    {
        $reopened = DB::table('usuario_rol')
            ->where('id_usuario', $user->id_usuario)
            ->where('id_rol', $roleId)
            ->where('fecha_inicio', $startedAt)
            ->update(['fecha_fin' => null]);

        if ($reopened === 0) {
            $user->roles()->attach($roleId, ['fecha_inicio' => $startedAt]);
        }
    }

    /** ¿La cuenta indicada está ACTIVA y tiene hoy el rol Administrador? Sin cuenta, nunca. */
    public function isAdministrator(?string $userId): bool
    {
        return $this->hasActiveRole($userId, Role::ADMINISTRADOR);
    }

    /**
     * Una cuenta de docente invitado (código INV) solo puede tener el rol Invitado; una cuenta con
     * código SIS, nunca. Sin rol (null) cualquiera de las dos es válida.
     */
    public function assertRoleFitsAccount(string $accountCode, ?string $roleName): void
    {
        if ($roleName === null) {
            return;
        }

        $isGuestAccount = SisCode::isGuest(SisCode::normalize($accountCode));

        if ($isGuestAccount && $roleName !== Role::INVITADO) {
            throw RoleNotAllowedForAccountException::guestAccount();
        }

        if (! $isGuestAccount && $roleName === Role::INVITADO) {
            throw RoleNotAllowedForAccountException::sisAccount();
        }
    }

    /** ¿La cuenta indicada está ACTIVA y ejerce hoy la docencia (Docente o Invitado)? Sin cuenta, nunca. */
    public function isTeacher(?string $userId): bool
    {
        return $this->canUse($userId, 'teaching');
    }

    /**
     * ¿La cuenta indicada está ACTIVA y su rol vigente ejerce la función dada?
     *
     * Las funciones y sus roles viven en config('sciem.access'), el único lugar que decide el acceso.
     */
    public function canUse(?string $userId, string $feature): bool
    {
        return $this->hasActiveRole($userId, $this->rolesFor($feature));
    }

    /** @return array<int, string> Roles que ejercen la función, según config('sciem.access'). */
    public function rolesFor(string $feature): array
    {
        $roles = config('sciem.access.' . $feature);

        if (! is_array($roles)) {
            throw new InvalidArgumentException("Función sin definir en sciem.access: {$feature}");
        }

        return $roles;
    }

    /** ¿La cuenta indicada está ACTIVA y tiene hoy alguno de los roles dados? Sin cuenta, nunca. */
    public function hasAnyActiveRole(?string $userId, array $roleNames): bool
    {
        return $this->hasActiveRole($userId, $roleNames);
    }

    /**
     * Una cuenta INACTIVA no ejerce su rol aunque conserve un token vigente: el token se emite al
     * iniciar sesión y la cuenta puede deshabilitarse después.
     *
     * @param  string|array<int, string>  $roleNames
     */
    private function hasActiveRole(?string $userId, $roleNames): bool
    {
        if ($userId === null) {
            return false;
        }

        return User::whereKey($userId)
            ->activos()
            ->whereHas('activeRoles', fn ($query) => $query->whereIn('nombre_rol', (array) $roleNames))
            ->exists();
    }

    public function findActiveRole(User $user): ?Role
    {
        return $user->rolActivo();
    }

    /**
     * Asignaciones académicas vigentes de la cuenta.
     *
     * Antes de cambiar el rol de un Docente, el Administrador debe saber si deja
     * grupos, materias o exámenes a su cargo en el periodo activo. Las materias se
     * derivan de los grupos: el docente no tiene vínculo directo con la materia.
     */
    public function countActiveAssignments(User $user): array
    {
        $activeGroups = DB::table('grupo')
            ->where('grupo.id_usuario_docente', $user->id_usuario)
            ->where('grupo.id_gestion_periodo', $this->subjectCatalog->activePeriodId())
            ->where('grupo.estado', RecordStatus::ACTIVE);

        $groupCount = (clone $activeGroups)->count();

        $subjectCount = (clone $activeGroups)
            ->distinct()
            ->count(DB::raw('(grupo.id_carrera, grupo.id_materia)'));

        $examCount = (clone $activeGroups)
            ->join('grupo_examen', 'grupo_examen.id_grupo', '=', 'grupo.id_grupo')
            ->join('examen', 'examen.id_examen', '=', 'grupo_examen.id_examen')
            ->whereDate('examen.fecha', '>=', now()->toDateString())
            ->distinct()
            ->count('examen.id_examen');

        return [
            'grupos'        => $groupCount,
            'materias'      => $subjectCount,
            'examenes'      => $examCount,
            // Materias y exámenes cuelgan de los grupos: sin grupos activos no hay nada más.
            'tiene_activas' => $groupCount > 0,
        ];
    }
}
