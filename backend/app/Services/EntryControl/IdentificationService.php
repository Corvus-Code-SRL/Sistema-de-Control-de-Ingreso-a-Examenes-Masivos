<?php

namespace App\Services\EntryControl;

use App\Models\User;

class IdentificationService
{
    private EntryControlSnapshotService $snapshots;

    public function __construct(EntryControlSnapshotService $snapshots)
    {
        $this->snapshots = $snapshots;
    }

    public function identify(int $examId, array $input): array
    {
        $record = isset($input['id_estudiante'])
            ? $this->snapshots->studentById($examId, (int) $input['id_estudiante'])
            : $this->snapshots->studentBySis($examId, (string) $input['cod_sis']);

        if ($record === null) {
            return ['veredicto' => 'NO_ENCONTRADO', 'registro' => null];
        }

        return ['veredicto' => 'IDENTIFICADO', 'registro' => $record];
    }

    public function searchByName(int $examId, User $actor, string $name): array
    {
        return $this->snapshots->search($examId, $actor, $name);
    }
}
