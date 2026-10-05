<?php

namespace App\Services\Academic\Importers;

use Illuminate\Support\Facades\DB;

/**
 * Crea en lote los estudiantes que la nómina trae y la base todavía no conoce.
 */
class StudentRosterStudentCreator
{
    /** 8 columnas por fila: 1000 filas son 8000 parámetros, muy por debajo del límite de PostgreSQL. */
    private const CHUNK_SIZE = 1000;

    private StudentRosterStudentMapper $mapper;

    public function __construct(StudentRosterStudentMapper $mapper)
    {
        $this->mapper = $mapper;
    }

    /**
     * Inserta con ON CONFLICT (cod_sis) DO NOTHING: si otro docente importó el mismo
     * estudiante en el mismo instante, la fila existente se reutiliza y no se falla ni se
     * duplica. Devuelve cuántos estudiantes se crearon de verdad.
     *
     * @param array<int, StudentRosterRow> $rows
     */
    public function createMany(array $rows): int
    {
        $created = 0;

        foreach (array_chunk($rows, self::CHUNK_SIZE) as $chunk) {
            $records = array_map(
                function (StudentRosterRow $row): array {
                    return $this->mapper->map($row);
                },
                $chunk
            );

            $columns = array_keys($records[0]);
            $rowPlaceholder = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';

            $bindings = [];

            foreach ($records as $record) {
                foreach ($columns as $column) {
                    $bindings[] = $record[$column];
                }
            }

            $created += DB::affectingStatement(
                'insert into estudiante (' . implode(', ', $columns) . ') values '
                . implode(', ', array_fill(0, count($records), $rowPlaceholder))
                . ' on conflict (cod_sis) do nothing',
                $bindings
            );
        }

        return $created;
    }
}
