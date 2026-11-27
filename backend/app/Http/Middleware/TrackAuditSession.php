<?php

namespace App\Http\Middleware;

use App\Support\Audit\AuditContext;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Entrega a AuditContext el token de la petición, para que cada fila de la bitácora quede unida a la
 * sesión (el dispositivo) desde la que se hizo. Va justo después de auth:sanctum y antes de account.active,
 * para que también la revocación de una cuenta deshabilitada quede en su sesión.
 *
 * No hace ninguna consulta: la sesión se busca solo si la petición escribe en la bitácora.
 */
class TrackAuditSession
{
    private AuditContext $context;

    public function __construct(AuditContext $context)
    {
        $this->context = $context;
    }

    /**
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        $token = $user === null ? null : $user->currentAccessToken();
        $key = $token instanceof Model ? $token->getKey() : null;

        // Un token simulado (Sanctum::actingAs en pruebas) no tiene clave: la operación queda sin sesión.
        $this->context->forToken(is_int($key) ? $key : null);

        return $next($request);
    }

    /** Al terminar la petición se olvida la sesión, para que no pase a la siguiente. */
    public function terminate($request, $response): void
    {
        $this->context->reset();
    }
}
