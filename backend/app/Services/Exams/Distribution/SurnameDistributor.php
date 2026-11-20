<?php

namespace App\Services\Exams\Distribution;

use App\Support\SisCode;
use App\Support\Text\SpanishSortKey;

/** Reparte habilitados en bloques alfabéticos proporcionales por el método de mayores restos. */
class SurnameDistributor
{
    /**
     * La nómina recibida contiene únicamente habilitados; no se consulta ni modifica la base.
     *
     * @param list<array{id_estudiante:int, apellido_paterno:string, apellido_materno:?string,
     *     nombre:string, cod_sis:string}> $students
     * @param list<array{id_ambiente:int, capacidad:int}> $rooms
     * @return array{assignments:array<int, int>, unassigned:list<int>}
     */
    public function distribute(array $students, array $rooms): array
    {
        $students = $this->orderedStudents($students);
        usort($rooms, fn (array $left, array $right): int => $left['id_ambiente'] <=> $right['id_ambiente']);
        $quotas = $this->quotas(count($students), $rooms);
        $assignments = [];
        $offset = 0;

        foreach ($rooms as $index => $room) {
            $end = $offset + $quotas[$index];

            while ($offset < $end) {
                $assignments[$students[$offset]['id_estudiante']] = $room['id_ambiente'];
                $offset++;
            }
        }

        return [
            'assignments' => $assignments,
            'unassigned' => array_column(array_slice($students, $offset), 'id_estudiante'),
        ];
    }

    /**
     * @param list<array{id_estudiante:int, apellido_paterno:string, apellido_materno:?string,
     *     nombre:string, cod_sis:string}> $students
     * @return list<array{id_estudiante:int, apellido_paterno:string, apellido_materno:?string,
     *     nombre:string, cod_sis:string}>
     */
    public function orderedStudents(array $students): array
    {
        $keys = [];

        foreach ($students as $student) {
            $keys[$student['id_estudiante']] = [
                SpanishSortKey::make($student['apellido_paterno']),
                SpanishSortKey::make($student['apellido_materno'] ?? ''),
                SpanishSortKey::make($student['nombre']),
                SisCode::normalize($student['cod_sis']),
            ];
        }

        usort($students, function (array $left, array $right) use ($keys): int {
            foreach ($keys[$left['id_estudiante']] as $index => $key) {
                $comparison = strcmp($key, $keys[$right['id_estudiante']][$index]);

                if ($comparison !== 0) {
                    return $comparison;
                }
            }

            return 0;
        });

        return $students;
    }

    /**
     * @param list<array{id_ambiente:int, capacidad:int}> $rooms
     * @return list<int>
     */
    private function quotas(int $studentCount, array $rooms): array
    {
        $totalCapacity = array_sum(array_column($rooms, 'capacidad'));
        $quotas = array_fill(0, count($rooms), 0);

        if ($totalCapacity === 0) {
            return $quotas;
        }

        $count = min($studentCount, $totalCapacity);
        $remainders = [];

        foreach ($rooms as $index => $room) {
            $numerator = $count * $room['capacidad'];
            $quotas[$index] = min($room['capacidad'], intdiv($numerator, $totalCapacity));
            $remainders[$index] = $numerator % $totalCapacity;
        }

        $order = array_keys($rooms);
        usort($order, fn (int $left, int $right): int =>
            ($remainders[$right] <=> $remainders[$left]) ?: ($left <=> $right));
        $remaining = $count - array_sum($quotas);

        foreach ($order as $index) {
            if ($remaining === 0) {
                break;
            }

            if ($quotas[$index] < $rooms[$index]['capacidad']) {
                $quotas[$index]++;
                $remaining--;
            }
        }

        return $quotas;
    }
}
