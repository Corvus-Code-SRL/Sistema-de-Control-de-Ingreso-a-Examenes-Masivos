<?php

namespace App\Support\Audit;

use App\Models\Session;

/**
 * Sesión (dispositivo) de la petición en curso, para unir cada fila de la bitácora con la sesión desde la que
 * se hizo.
 *
 * No resuelve a nadie por su cuenta: el middleware TrackAuditSession le entrega el token de la petición y
 * lo olvida al terminar. Sin token (tareas programadas, seeders, comandos) la sesión es null. Una prueba
 * decide la sesión con forToken() o forSession(), igual que decide al usuario pasándolo por argumento.
 *
 * Se registra como singleton: es la misma instancia durante toda la petición.
 */
class AuditContext
{
    private ?int $tokenId = null;

    private ?int $sessionId = null;

    private bool $resolved = true;

    /** El token con el que se autenticó la petición; la sesión se busca solo si se escribe en la bitácora. */
    public function forToken(?int $tokenId): void
    {
        $this->tokenId = $tokenId !== null && $tokenId > 0 ? $tokenId : null;
        $this->sessionId = null;
        $this->resolved = $this->tokenId === null;
    }

    /** Una sesión ya conocida (por ejemplo, la que se acaba de abrir). */
    public function forSession(?int $sessionId): void
    {
        $this->tokenId = null;
        $this->sessionId = $sessionId;
        $this->resolved = true;
    }

    public function reset(): void
    {
        $this->forToken(null);
    }

    /** Una sola consulta por petición, y solo cuando hace falta. */
    public function sessionId(): ?int
    {
        if (! $this->resolved) {
            $id = Session::where('id_token', $this->tokenId)->value('id_sesion');
            $this->sessionId = $id === null ? null : (int) $id;
            $this->resolved = true;
        }

        return $this->sessionId;
    }
}
