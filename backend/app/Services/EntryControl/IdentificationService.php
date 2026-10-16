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
        if (isset($input['id_estudiante'])) {
            $record = $this->snapshots->studentById($examId, (int) $input['id_estudiante']);
        } elseif (! empty($input['cod_sis'])) {
            $record = $this->snapshots->studentBySis($examId, (string) $input['cod_sis']);
        } else {
            $record = $this->snapshots->studentByCi($examId, (string) $input['ci']);
        }

        if ($record === null) {
            return ['veredicto' => 'NO_ENCONTRADO', 'registro' => null];
        }

        return ['veredicto' => 'IDENTIFICADO', 'registro' => $record];
    }

    public function assistantBySis(int $examId, string $sis): ?array
    {
        return $this->snapshots->assistantBySis($examId, $sis);
    }

    public function searchByName(int $examId, User $actor, string $name): array
    {
        return $this->snapshots->search($examId, $actor, $name);
    }

    public function searchByNumberPrefix(int $examId, User $actor, string $field, string $prefix): array
    {
        return $this->snapshots->searchByNumberPrefix($examId, $actor, $field, $prefix);
    }
}
