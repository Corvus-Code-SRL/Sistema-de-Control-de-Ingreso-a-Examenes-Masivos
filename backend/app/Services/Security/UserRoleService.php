<?php

namespace App\Services\Security;

use App\Exceptions\Security\SelfRoleAssignmentException;
use App\Models\Role;
use App\Models\User;
use App\Services\Academic\SubjectCatalogService;
use App\Support\RecordStatus;
use Illuminate\Support\Facades\DB;

/**
 * Asigna, modifica y consulta el rol de una cuenta.
 *
 * Una cuenta tiene un solo rol vigente, pero el historial no se borra: al
 * cambiar de rol se cierra el anterior con fecha_fin y se inserta el nuevo.
 */
class UserRoleService
{
    private AuditLogService $auditLog;

    private CurrentUserService $currentUser;

    private SubjectCatalogService $subjectCatalog;

    public function __construct(
        AuditLogService $auditLog,
        CurrentUserService $currentUser,
        SubjectCatalogService $subjectCatalog
    ) {
        $this->auditLog = $auditLog;
        $this->currentUser = $currentUser;
        $this->subjectCatalog = $subjectCatalog;
    }

    public function assignRole(User $user, int $roleId): Role
    {
        if ($this->currentUser->id() === $user->id_usuario) {
            throw new SelfRoleAssignmentException();
        }

        return DB::transaction(function () use ($user, $roleId) {
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

            if ($previousRole) {
                DB::table('usuario_rol')
                    ->where('id_usuario', $user->id_usuario)
                    ->where('id_rol', $previousRole->id_rol)
                    ->whereNull('fecha_fin')
                    ->update(['fecha_fin' => now()]);
            }

            $user->roles()->attach($roleId, ['fecha_inicio' => now()]);

            // El estado de la cuenta no se toca: asignar rol no habilita una cuenta deshabilitada.

            $newRole = Role::findOrFail($roleId);

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
                ]
            );

            return $newRole;
        });
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
            ->where('grupo.id_periodo', $this->subjectCatalog->activePeriodId())
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
