<?php

namespace App\Support\Cache;

use DateTimeInterface;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/** Conserva el último motivo de apertura fallida hasta el fin del control. */
class EntryOpeningFailureStore
{
    public function put(int $examId, string $code, string $message, DateTimeInterface $expiresAt): void
    {
        if (! $this->cache()->put($this->key($examId), [
            'codigo' => $code,
            'mensaje' => $message,
        ], $expiresAt)) {
            throw new RuntimeException('No se pudo guardar el motivo de apertura fallida.');
        }
    }

    public function get(int $examId): ?array
    {
        $failure = $this->cache()->get($this->key($examId));

        return is_array($failure) ? $failure : null;
    }

    public function forget(int $examId): void
    {
        $this->cache()->forget($this->key($examId));
    }

    private function cache(): Repository
    {
        return Cache::store(config('sciem.entry_control_cache_store', 'redis'));
    }

    private function key(int $examId): string
    {
        return "entry-control:{$examId}:opening-failure";
    }
}
