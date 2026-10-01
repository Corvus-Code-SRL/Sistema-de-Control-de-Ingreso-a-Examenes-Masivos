<?php

namespace App\Services\Exams;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

/**
 * Gestiona el latido (heartbeat) de los usuarios conectados al control de ingreso.
 */
class PresenciaControlService
{
    /**
     * Registra que el usuario actual sigue en linea.
     */
    public function registerHeartbeat(int $examId, string $userId, string $userName): void
    {
        $cacheKey = "exam_{$examId}_connected_users";
        $connected = $this->cache()->get($cacheKey, []);

        // Actualizamos o insertamos al usuario con su timestamp actual
        $connected[$userId] = [
            'nombre'        => $userName,
            'ultimo_latido' => now()->timestamp,
        ];

        // Guardamos el array en Redis con un tiempo de vida máximo de 30 segundos
        $this->cache()->put($cacheKey, $connected, 30);
    }

    /**
     * Devuelve la lista de usuarios que han emitido un latido en los ultimos 15 segundos
     */
    public function getConnectedUsers(int $examId): array
    {
        $cacheKey = "exam_{$examId}_connected_users";
        $connected = $this->cache()->get($cacheKey, []);
        
        $activeUsers = [];
        $now = now()->timestamp;

        foreach ($connected as $data) {
            //Conectado si latip hace 15 segundos o menos
            if (($now - $data['ultimo_latido']) <= 15) {
                $activeUsers[] = $data;
            }
        }

        return $activeUsers;
    }

    private function cache(): Repository
    {
        return Cache::store(config("sciem.entry_control_cache_store", "redis"));
    }
}