<?php

namespace App\Services\Security;

use App\Exceptions\Security\InactiveAccountException;
use App\Exceptions\Security\InvalidCredentialsException;
use App\Exceptions\Security\NoCurrentRoleException;
use App\Models\Role;
use App\Models\User;
use App\Support\RecordStatus;
use App\Support\SisCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Inicio y cierre de sesión con tokens de Sanctum.
 *
 * Recibe todo lo que necesita como argumento (IP, token en uso, usuario): no consulta la sesión por su cuenta.
 */
class AuthService
{
    private const TOKEN_NAME = 'sesion';

    /** Hash de relleno: se verifica contra él cuando la cuenta no existe, para igualar los tiempos. */
    private const DUMMY_HASH = '$2y$10$P58tp9.8/TaCQEV2DsgZ5OqXr76VkWWafQ79u7mAZ7brbjKUhw5M.';

    private AuditLogService $auditLog;

    public function __construct(AuditLogService $auditLog)
    {
        $this->auditLog = $auditLog;
    }

    /**
     * @return array{usuario: User, rol: Role, token: string, expira_en: ?Carbon}
     */
    public function iniciarSesion(string $codSis, string $password, string $ip): array
    {
        $codSis = SisCode::normalize($codSis);
        $user = User::where('cod_sis', $codSis)->first();

        if ($user === null) {
            Hash::check($password, self::DUMMY_HASH);
            $this->registrarIntentoFallido($codSis, $ip, 'credenciales_invalidas', null);

            throw new InvalidCredentialsException();
        }

        if (! Hash::check($password, $user->getAuthPassword())) {
            $this->registrarIntentoFallido($codSis, $ip, 'credenciales_invalidas', $user);

            throw new InvalidCredentialsException();
        }

        // El estado y el rol solo se revelan con la contraseña correcta.
        if ($user->estado !== RecordStatus::ACTIVE) {
            $this->registrarIntentoFallido($codSis, $ip, 'cuenta_inactiva', $user);

            throw new InactiveAccountException();
        }

        $role = $this->currentRole($user);

        if ($role === null) {
            $this->registrarIntentoFallido($codSis, $ip, 'sin_rol_vigente', $user);

            throw new NoCurrentRoleException();
        }

        $newToken = $user->createToken(self::TOKEN_NAME);
        $newToken->accessToken->forceFill(['password_confirmado_en' => now()])->save();

        $minutes = config('sanctum.expiration');

        return [
            'usuario' => $user,
            'rol' => $role,
            'token' => $newToken->plainTextToken,
            'expira_en' => $minutes ? now()->addMinutes($minutes) : null,
        ];
    }

    /** Revoca solo el token en uso; las demás sesiones de la cuenta siguen abiertas. */
    public function cerrarSesion($token): void
    {
        if ($token instanceof Model) {
            $token->delete();
        }
    }

    /**
     * Cuenta, rol vigente y permisos de navegación, para reconstruir la sesión al cargar la página.
     *
     * @return array{usuario: User, rol: Role, navegacion: array, password_confirmado_en: ?Carbon}
     */
    public function sesionActual(User $user, $token): array
    {
        if ($user->estado !== RecordStatus::ACTIVE) {
            throw new InactiveAccountException();
        }

        $role = $this->currentRole($user);

        if ($role === null) {
            throw new NoCurrentRoleException();
        }

        return [
            'usuario' => $user,
            'rol' => $role,
            'navegacion' => $this->navigationFor($role),
            'password_confirmado_en' => $token instanceof Model ? $token->password_confirmado_en : null,
        ];
    }

    /** Verifica la contraseña del usuario y renueva el momento de confirmación de la sesión. */
    public function confirmarPassword(User $user, string $password, $token): Carbon
    {
        if (! Hash::check($password, $user->getAuthPassword())) {
            throw ValidationException::withMessages([
                'password' => ['La contraseña es incorrecta.'],
            ]);
        }

        $confirmedAt = now();

        if ($token instanceof Model) {
            $token->forceFill(['password_confirmado_en' => $confirmedAt])->save();
        }

        return $confirmedAt;
    }

    /**
     * Rol vigente: la asignación abierta (fecha_fin nula), en una sola consulta. El esquema no
     * impide varias abiertas a la vez, así que se toma la más reciente.
     */
    private function currentRole(User $user): ?Role
    {
        return $user->activeRoles()
            ->orderByDesc('usuario_rol.fecha_inicio')
            ->first();
    }

    /**
     * Permisos del rol y las pantallas (ui) que habilitan, en una sola consulta.
     * Una fila de permiso_rol, permiso o ui_permiso INACTIVA no concede nada.
     *
     * @return array{permisos: array<int, string>, interfaces: array<int, string>}
     */
    private function navigationFor(Role $role): array
    {
        $rows = DB::table('permiso_rol as pr')
            ->join('permiso as p', 'p.id_permiso', '=', 'pr.id_permiso')
            ->leftJoin('ui_permiso as up', function ($join) {
                $join->on('up.id_permiso', '=', 'p.id_permiso')
                    ->whereRaw('up.estado::text = ?', [RecordStatus::ACTIVE]);
            })
            ->leftJoin('ui', function ($join) {
                $join->on('ui.id_ui', '=', 'up.id_ui')
                    ->whereRaw('ui.estado::text = ?', [RecordStatus::ACTIVE]);
            })
            ->where('pr.id_rol', $role->id_rol)
            ->whereRaw('pr.estado::text = ?', [RecordStatus::ACTIVE])
            ->whereRaw('p.estado::text = ?', [RecordStatus::ACTIVE])
            ->orderBy('p.nombre_permiso')
            ->orderBy('ui.nombre_ui')
            ->get(['p.nombre_permiso', 'ui.nombre_ui']);

        return [
            'permisos' => $rows->pluck('nombre_permiso')->unique()->values()->all(),
            'interfaces' => $rows->pluck('nombre_ui')->filter()->unique()->values()->all(),
        ];
    }

    /**
     * Deja constancia del intento fallido con el identificador, la IP y el motivo; la hora la
     * pone la bitácora. log.id_usuario es NOT NULL: si el código no corresponde a ninguna
     * cuenta no hay a quién atribuirlo, y el intento va al log de la aplicación.
     */
    private function registrarIntentoFallido(string $codSis, string $ip, string $reason, ?User $user): void
    {
        $detail = ['cod_sis' => $codSis, 'ip' => $ip, 'motivo' => $reason];

        if ($user === null) {
            Log::warning('Intento de inicio de sesión fallido', $detail + ['fecha_hora' => now()->toIso8601String()]);

            return;
        }

        $this->auditLog->registrar('INICIO_SESION_FALLIDO', 'usuario', null, $detail, $user->id_usuario);
    }
}
