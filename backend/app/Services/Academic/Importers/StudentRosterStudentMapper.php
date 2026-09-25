<?php

namespace App\Services\Academic\Importers;

use App\Support\RecordStatus;

final class StudentRosterStudentMapper
{
    /**
     * El CI queda en NULL: la nómina no lo trae y un CI inventado nunca coincidiría
     * con el documento real en la puerta. Lo captura StudentCiService en la primera
     * verificación (EX04).
     *
     * @return array<string, string|null>
     */
    public function map(StudentRosterRow $row): array
    {
        return [
            'cod_sis' => $row->sisCode(),
            'ci' => null,
            'nombre' => $row->firstNames(),
            'apellido_paterno' => $row->lastNames(),
            'apellido_materno' => null,
            'correo_institucional' => null,
            'telefono' => null,
            'estado' => RecordStatus::ACTIVE,
        ];
    }
}
