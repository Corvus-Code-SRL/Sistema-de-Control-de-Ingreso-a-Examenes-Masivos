<?php

namespace App\Services\EntryControl;

use App\Models\Student;
use App\Models\User;

class IdentificationService
{
    /** Veredicto de identify() cuando encuentra al estudiante. */
    public const IDENTIFIED = 'IDENTIFICADO';

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
            $record = $this->snapshots->studentBySis($examId, (string) $input['cod_sis'])
                ?? $this->outsideStudentBy('cod_sis', (string) $input['cod_sis']);
        } else {
            $record = $this->snapshots->studentByCi($examId, (string) $input['ci'])
                ?? $this->outsideStudentBy('ci', (string) $input['ci']);
        }

        if ($record === null) {
            return ['veredicto' => 'NO_ENCONTRADO', 'registro' => null];
        }

        return ['veredicto' => self::IDENTIFIED, 'registro' => $record];
    }

    /**
     * Consulta solo el identificador exacto presentado; nunca añade ajenos al snapshot.
     * El ingreso con retraso (HU-037) la usa para el estudiante que no pertenece al examen.
     */
    public function outsideStudentBy(string $column, string $value): ?array
    {
        $student = Student::query()->where($column, $value)->first([
            'id_estudiante', 'cod_sis', 'ci', 'foto_url',
            'nombre', 'apellido_paterno', 'apellido_materno',
        ]);
        if ($student === null) {
            return null;
        }

        return [
            'estudiante' => [
                'id_estudiante' => (int) $student->id_estudiante,
                'cod_sis' => $student->cod_sis,
                'ci' => $student->ci,
                'ci_pendiente' => $student->ci === null,
                'nombre_completo' => $student->nombre_completo,
                'carrera' => null,
                'foto_url' => $student->foto_url,
            ],
            'grupo' => null,
            'estado_habilitacion' => null,
            'ambiente_asignado' => null,
            'ingreso_previo' => null,
        ];
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
