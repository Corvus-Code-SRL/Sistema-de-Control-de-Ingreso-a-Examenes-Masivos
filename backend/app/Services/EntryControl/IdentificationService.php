<?php

namespace App\Services\EntryControl;

use App\Models\Student;
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

    public function searchByNumberPrefix(int $examId, User $actor, string $field, string $prefix): array
    {
        $this->snapshots->assertCanControl($examId, $actor);

        $column = $field === 'sis' ? 'cod_sis' : 'ci';

        return Student::query()
            ->where($column, 'like', $prefix . '%')
            ->orderBy($column)
            ->limit(10)
            ->get(['id_estudiante', 'cod_sis', 'nombre', 'apellido_paterno', 'apellido_materno'])
            ->map(fn (Student $student) => [
                'id_estudiante' => (int) $student->id_estudiante,
                'cod_sis' => $student->cod_sis,
                'nombre_completo' => trim(
                    $student->nombre . ' ' . $student->apellido_paterno . ' ' . $student->apellido_materno
                ),
            ])
            ->all();
    }
}
