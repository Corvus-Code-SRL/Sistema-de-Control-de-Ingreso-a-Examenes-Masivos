<?php

namespace App\Services\Security;

use App\Models\Session;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Registro de sesiones: abre una fila de `sesion` por cada token de Sanctum y la cierra cuando el token
 * deja de valer. Cada apertura y cada cierre queda en la bitácora (INICIO_SESION / CIERRE_SESION).
 *
 * La sesión es la del dispositivo que inició sesión: la IP y el navegador (User-Agent) son los de ese
 * inicio y no se actualizan en cada petición.
 */
class SessionRegistry
{
    private const AGENT_LENGTH = 255;

    private AuditLogService $auditLog;

    public function __construct(AuditLogService $auditLog)
    {
        $this->auditLog = $auditLog;
    }

    /** Abre la sesión del token recién creado. */
    public function open(User $user, Model $token, string $ip, ?string $agent): Session
    {
        $agent = $agent === null || trim($agent) === '' ? null : Str::limit(trim($agent), self::AGENT_LENGTH, '');

        $session = Session::create([
            'id_usuario' => $user->id_usuario,
            'id_token' => (int) $token->getKey(),
            'ip_direccion' => $ip,
            'agente' => $agent,
            'hora_ingreso' => now(),
        ]);

        $this->auditLog->registrar('INICIO_SESION', 'sesion', null, [
            'id_sesion' => (int) $session->id_sesion,
            'id_usuario' => $user->id_usuario,
            'ip_direccion' => $ip,
            'agente' => $agent,
        ], $user->id_usuario, (int) $session->id_sesion);

        return $session;
    }

    /**
     * Cierra la sesión abierta de un token. Sin sesión abierta (un token anterior a este registro) no hace nada.
     *
     * Todo cierre lo firma la dueña de la sesión: `motivo_salida` dice si salió ella, si venció o si se revocó.
     */
    public function close(Model $token, string $reason): void
    {
        $session = Session::where('id_token', (int) $token->getKey())->whereNull('hora_salida')->first();

        if ($session !== null) {
            $this->finish($session, $reason, now());
        }
    }

    /** Cierra todas las sesiones abiertas de la cuenta salvo la del token indicado. */
    public function closeOthers(User $user, ?Model $keep, string $reason): void
    {
        $sessions = Session::where('id_usuario', $user->id_usuario)
            ->whereNull('hora_salida')
            ->when($keep !== null, function ($query) use ($keep) {
                $query->where(function ($other) use ($keep) {
                    $other->whereNull('id_token')->orWhere('id_token', '!=', (int) $keep->getKey());
                });
            })
            ->get();

        foreach ($sessions as $session) {
            $this->finish($session, $reason, now());
        }
    }

    /**
     * Cierra como EXPIRADA cada sesión abierta cuyo token ya venció (o ya no existe) y borra los tokens
     * vencidos. La hora de salida es la del vencimiento, no la de esta tarea.
     *
     * @return int sesiones cerradas
     */
    public function closeExpired(): int
    {
        $minutes = (int) config('sanctum.expiration');
        $limit = $minutes > 0 ? now()->subMinutes($minutes) : null;

        return DB::transaction(function () use ($minutes, $limit) {
            $sessions = Session::query()
                ->leftJoin('personal_access_tokens as t', 't.id', '=', 'sesion.id_token')
                ->whereNull('sesion.hora_salida')
                ->where(function ($expired) use ($limit) {
                    $expired->whereNull('t.id');

                    if ($limit !== null) {
                        $expired->orWhere('t.created_at', '<=', $limit);
                    }
                })
                ->get(['sesion.*', 't.created_at as token_creado']);

            foreach ($sessions as $session) {
                $this->finish($session, Session::EXPIRED, $this->expiredAt($session, $minutes));
            }

            if ($limit !== null) {
                DB::table('personal_access_tokens')->where('created_at', '<=', $limit)->delete();
            }

            return $sessions->count();
        });
    }

    /** Vencimiento del token (creación + minutos de Sanctum) o, sin token, la hora de ingreso. */
    private function expiredAt(Session $session, int $minutes): Carbon
    {
        $created = $session->getAttribute('token_creado');

        if ($created === null || $minutes <= 0) {
            return $session->hora_ingreso->copy();
        }

        $expires = Carbon::parse($created)->addMinutes($minutes);

        return $expires->lessThan($session->hora_ingreso) ? $session->hora_ingreso->copy() : $expires;
    }

    private function finish(Session $session, string $reason, Carbon $at): void
    {
        $session->setRawAttributes(array_diff_key($session->getAttributes(), ['token_creado' => true]), true);
        $session->forceFill(['hora_salida' => $at, 'motivo_salida' => $reason])->save();

        $this->auditLog->registrar('CIERRE_SESION', 'sesion', [
            'id_sesion' => (int) $session->id_sesion,
            'id_usuario' => $session->id_usuario,
            'hora_salida' => null,
            'motivo_salida' => null,
        ], [
            'id_sesion' => (int) $session->id_sesion,
            'id_usuario' => $session->id_usuario,
            'hora_salida' => $at->copy()->utc()->toIso8601String(),
            'motivo_salida' => $reason,
        ], $session->id_usuario);
    }
}
