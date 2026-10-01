<?php

namespace App\Services\Exams;

use App\Models\User;
use App\Services\EntryControl\EntryControlSnapshotService;

/** Resumen preparado en Redis para el polling del control de ingreso. */
class RedControlService
{
    private EntryControlSnapshotService $snapshots;

    public function __construct(EntryControlSnapshotService $snapshots)
    {
        $this->snapshots = $snapshots;
    }

    public function currentStatus(int $examId, User $actor, ?string $clientVersion): array
    {
        return $this->snapshots->currentStatus($examId, $actor, $clientVersion);
    }
}
