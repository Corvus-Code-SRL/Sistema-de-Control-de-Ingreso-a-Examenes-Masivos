<?php

namespace App\Services\RiskCenter\Academic;

use App\Models\InactiveTeacherDetection;
use App\Models\User;
use App\Services\Security\UserRoleService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Único lugar que decide si el docente que aprobó un reporte sigue activo y desde cuándo no lo está (HU-062).
 * Si la fuente de esa fecha cambia, se cambia solo aquí.
 *
 * Regla vigente: el docente está inactivo si su cuenta no está ACTIVA o ya no tiene un rol docente (los de
 * sciem.access.risk_rectify_confirm). La fecha de inactividad es:
 *  - ROL_CERRADO: el cierre (usuario_rol.fecha_fin) de su último rol docente, cuando ya no tiene ninguno abierto.
 *  - PRIMERA_DETECCION: si no hay fecha de cierre (cuenta no ACTIVA con el rol aún abierto, o un reporte sin
 *    aprobador), la primera vez que un Administrador intentó enviarle una solicitud o rectificar uno de sus
 *    registros. Esa fecha se guarda en docente_inactivo_deteccion para que los N días sean reales y no se
 *    reinicien. Nunca se usa la fecha en que el registro entró a la Central.
 *
 * Regla futura (no implementada): cuando exista la gestión de habilitar y deshabilitar cuentas, la fecha de
 * inactividad saldrá SOLO de la fecha de deshabilitación de la cuenta, porque una cuenta puede conservar su rol
 * estando deshabilitada. Entonces ROL_CERRADO y PRIMERA_DETECCION dejan de usarse.
 *
 * Limitación conocida: si un docente vuelve a estar activo y deja de estarlo otra vez sin que nadie intente
 * rectificar entre ambos momentos, la detección anterior se conserva. Solo la limpia una consulta con
 * `$record = true` hecha mientras está activo.
 */
class ApprovingTeacherStatusService
{
    private UserRoleService $roles;

    public function __construct(UserRoleService $roles)
    {
        $this->roles = $roles;
    }

    /**
     * Estado del docente aprobador del registro. Con `$record = true` guarda la primera detección si hace falta;
     * el resto de las consultas son de solo lectura.
     *
     * Un reporte sin aprobador (datos antiguos) se trata como docente inactivo con PRIMERA_DETECCION, guardada
     * por registro porque no hay cuenta a la que atarla.
     */
    public function resolve(?string $teacherId, int $recordId, bool $record = false): ApprovingTeacherStatus
    {
        if ($teacherId !== null && $this->isActive($teacherId)) {
            if ($record) {
                InactiveTeacherDetection::query()->where('id_usuario', $teacherId)->delete();
            }

            return new ApprovingTeacherStatus(true, null, null, null);
        }

        $closedAt = $teacherId === null ? null : $this->lastTeachingRoleClosedAt($teacherId);

        if ($closedAt !== null) {
            return $this->inactive($closedAt, ApprovingTeacherStatus::SOURCE_ROLE_CLOSED);
        }

        $detectedAt = $this->firstDetection($teacherId, $recordId, $record);

        if ($detectedAt === null) {
            return new ApprovingTeacherStatus(false, null, null, null);
        }

        return $this->inactive($detectedAt, ApprovingTeacherStatus::SOURCE_FIRST_DETECTION);
    }

    private function inactive(Carbon $since, string $source): ApprovingTeacherStatus
    {
        $days = (int) config('sciem.rectificacion.dias_docente_inactivo', 180);

        return new ApprovingTeacherStatus(false, $since, $source, $since->copy()->addDays($days));
    }

    private function isActive(string $teacherId): bool
    {
        return $this->roles->canUse($teacherId, 'risk_rectify_confirm');
    }

    /** Cierre del último rol docente, solo si la cuenta ya no tiene ninguno abierto. */
    private function lastTeachingRoleClosedAt(string $teacherId): ?Carbon
    {
        $roleNames = $this->roles->rolesFor('risk_rectify_confirm');

        $teaching = DB::table('usuario_rol')
            ->join('rol', 'rol.id_rol', '=', 'usuario_rol.id_rol')
            ->where('usuario_rol.id_usuario', $teacherId)
            ->whereIn('rol.nombre_rol', $roleNames);

        if ((clone $teaching)->whereNull('usuario_rol.fecha_fin')->exists()) {
            return null;
        }

        $closedAt = $teaching->max('usuario_rol.fecha_fin');

        return $closedAt === null ? null : Carbon::parse($closedAt);
    }

    private function firstDetection(?string $teacherId, int $recordId, bool $record): ?Carbon
    {
        $key = $teacherId !== null ? ['id_usuario' => $teacherId] : ['id_cr_estudiante' => $recordId];

        $detection = InactiveTeacherDetection::query()->where($key)->first();

        if ($detection === null && $record) {
            // Los índices únicos protegen a dos intentos simultáneos: gana el primero y la fecha no se mueve.
            DB::table('docente_inactivo_deteccion')->insertOrIgnore($key + ['detectado_en' => Carbon::now()]);

            $detection = InactiveTeacherDetection::query()->where($key)->first();
        }

        return $detection?->detectado_en;
    }
}
