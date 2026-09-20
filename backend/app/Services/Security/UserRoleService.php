<?php

namespace App\Services\Security;

use App\Exceptions\Security\AutoAsignacionRolException;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UserRoleService
{
    private AuditLogService $auditoria;

    public function __construct(AuditLogService $auditoria)
    {
        $this->auditoria = $auditoria;
    }

    /**
     * Asigna o modifica el rol de una cuenta.
     *
     * Regla central (CA 3 + CA 9): un usuario tiene UN SOLO rol vigente,
     * pero el historial no se borra. Al cambiar de rol se CIERRA el
     * anterior con fecha_fin y se inserta el nuevo.
     */
    public function asignar(User $usuario, int $idRol, ?string $idUsuarioEjecutor = null): Role
    {
        // CA 14 — un Administrador no puede modificar su propio rol
        if ($idUsuarioEjecutor !== null && $idUsuarioEjecutor === $usuario->id_usuario) {
            throw new AutoAsignacionRolException();
        }

        $rolAnterior = $usuario->rolActivo();

        // CA 13 — si el rol no cambia realmente, no se registra nada
        if ($rolAnterior && (int) $rolAnterior->id_rol === $idRol) {
            return $rolAnterior;
        }

        return DB::transaction(function () use ($usuario, $idRol, $rolAnterior) {

            // CA 3 + CA 9 — cerrar el rol anterior sin eliminarlo
            if ($rolAnterior) {
                DB::table('usuario_rol')
                    ->where('id_usuario', $usuario->id_usuario)
                    ->where('id_rol', $rolAnterior->id_rol)
                    ->whereNull('fecha_fin')
                    ->update(['fecha_fin' => now()]);
            }

            // Insertar el nuevo rol vigente
            $usuario->roles()->attach($idRol, ['fecha_inicio' => now()]);

            // CA 10 — NO se toca usuario.estado: asignar rol a una cuenta
            // deshabilitada no la habilita.

            // CA 17 — trazabilidad con rol anterior y nuevo
            $this->auditoria->registrar(
                $rolAnterior ? 'MODIFICAR' : 'ASIGNAR_ROL',
                'usuario_rol',
                $rolAnterior
                    ? ['id_rol' => $rolAnterior->id_rol, 'nombre_rol' => $rolAnterior->nombre_rol]
                    : null,
                ['id_rol' => $idRol, 'id_usuario' => $usuario->id_usuario]
            );

            return Role::findOrFail($idRol);
        });
    }

    /** CA 16 — consultar el rol actualmente asignado. */
    public function consultarRolActual(User $usuario): ?Role
    {
        return $usuario->rolActivo();
    }

    /**
     * CA 15 — antes de cambiar el rol de un Docente, el frontend consulta
     * si esa cuenta tiene asignaciones activas para poder advertir al
     * Administrador antes de confirmar.
     */
    public function contarAsignacionesActivas(User $usuario): array
    {
        $grupos = DB::table('grupo')
            ->where('id_usuario_docente', $usuario->id_usuario)
            ->whereRaw('estado::text = ?', ['ACTIVO'])
            ->count();

        return [
            'grupos'        => $grupos,
            'tiene_activas' => $grupos > 0,
        ];
    }
}