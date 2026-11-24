<?php

namespace App\Services\Exams\Distribution;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Copia en Redis de «dejó de usarse la distribución», para que verificar siga sin consultar PostgreSQL.
 * Si Redis falla solo se mostraría un aviso de más al verificar: al registrar manda PostgreSQL.
 */
class DistributionSuspensionFlag
{
    private const KEY = 'entry-control:%d:distribution-suspended';

    public function mark(int $examId): void
    {
        try {
            $this->cache()->put(
                sprintf(self::KEY, $examId),
                true,
                now()->addHours((int) config('sciem.distribucion_suspendida_ttl_horas'))
            );
        } catch (Throwable $error) {
            Log::warning('HU-031: no se pudo marcar en Redis la distribución suspendida', [
                'id_examen' => $examId,
                'motivo' => $error->getMessage(),
            ]);
        }
    }

    public function isMarked(int $examId): bool
    {
        return (bool) $this->cache()->get(sprintf(self::KEY, $examId), false);
    }

    private function cache(): Repository
    {
        return Cache::store(config('sciem.entry_control_cache_store', 'redis'));
    }
}
