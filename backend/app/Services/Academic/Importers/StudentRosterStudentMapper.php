<?php

namespace App\Services\Academic\Importers;

use App\Support\RecordStatus;

final class StudentRosterStudentMapper
{
    /**
     * @return array<string, string|null>
     */
    public function map(
        StudentRosterRow $row,
        string $temporaryCi
    ): array {
        return [
            'cod_sis' => $row->sisCode(),
            'ci' => $temporaryCi,
            'nombre' => $row->firstNames(),
            'apellido_paterno' => $row->lastNames(),
            'apellido_materno' => null,
            'correo_institucional' => null,
            'telefono' => null,
            'estado' => RecordStatus::ACTIVE,
        ];
    }
}