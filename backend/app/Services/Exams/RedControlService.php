<?php

namespace App\Services\Exams;

use App\Models\Exam;
use App\Models\User;
use App\Services\EntryControl\EntryAccessService;
use App\Services\EntryControl\EntryControlSnapshotService;
use App\Support\Cache\EntryOpeningFailureStore;

/** Consulta la apertura pendiente o el resumen operativo preparado en Redis. */
class RedControlService
{
    private EntryControlSnapshotService $snapshots;
    private EntryAccessService $access;
    private EntryOpeningFailureStore $failures;
    private PresenciaControlService $presence;

    public function __construct(
        EntryControlSnapshotService $snapshots,
        EntryAccessService $access,
        EntryOpeningFailureStore $failures,
        PresenciaControlService $presence
    ) {
        $this->snapshots = $snapshots;
        $this->access = $access;
        $this->failures = $failures;
        $this->presence = $presence;
    }

    public function currentStatus(int $examId, User $actor, ?string $clientVersion): array
    {
        if (! $this->snapshots->ready($examId)) {
            $exam = Exam::query()->findOrFail($examId);
            $this->access->assertCanControl($exam, $actor);

            if ($exam->estado === Exam::PROGRAMADO) {
                return [
                    'estado' => Exam::PROGRAMADO,
                    'motivo_apertura' => $this->failures->get($examId),
                ];
            }
        }

        $status = $this->snapshots->currentStatus($examId, $actor, $clientVersion);
        $this->presence->registerHeartbeat($examId, $actor->id_usuario, $actor->nombre);
        $status['conectados'] = $this->presence->getConnectedUsers($examId);

        return $status;
    }
}
