<?php

namespace App\Services\Exams\Distribution;

use App\Support\Exams\DistributionMessages;
use Illuminate\Validation\ValidationException;

/**
 * Reglas de integridad que se comprueban con las fuentes bloqueadas dentro de la transacción.
 *
 * @phpstan-import-type DistributionStudent from DistributionRosterQuery
 * @phpstan-import-type DistributionRoom from DistributionRosterQuery
 */
class DistributionAssignmentValidator
{
    public const MIN_ROOMS = 2;

    /** @param list<DistributionRoom> $rooms */
    public function assertMultipleRooms(array $rooms): void
    {
        if (count($rooms) < self::MIN_ROOMS) {
            throw ValidationException::withMessages(['asignaciones' => DistributionMessages::MULTIPLE_ROOMS_REQUIRED]);
        }
    }

    /**
     * @param list<DistributionStudent> $students
     * @param list<DistributionRoom> $rooms
     * @param list<array{id_estudiante:int, id_ambiente:int}> $assignments
     */
    public function validate(array $students, array $rooms, array $assignments): void
    {
        $this->assertMultipleRooms($rooms);
        $missingSeats = count($students) - array_sum(array_column($rooms, 'capacidad'));

        if ($missingSeats > 0) {
            throw ValidationException::withMessages([
                'asignaciones' => sprintf(DistributionMessages::MISSING_SEATS, $missingSeats),
            ]);
        }

        $expected = array_column($students, 'id_estudiante');
        $received = array_column($assignments, 'id_estudiante');
        sort($expected, SORT_NUMERIC);
        sort($received, SORT_NUMERIC);

        if ($expected !== $received) {
            throw ValidationException::withMessages(['asignaciones' => DistributionMessages::INCOMPLETE_ROSTER]);
        }

        $capacity = array_column($rooms, 'capacidad', 'id_ambiente');

        foreach ($assignments as $assignment) {
            $roomId = $assignment['id_ambiente'];

            if (! isset($capacity[$roomId])) {
                throw ValidationException::withMessages(['asignaciones' => DistributionMessages::FOREIGN_ROOM]);
            }

            if (--$capacity[$roomId] < 0) {
                throw ValidationException::withMessages(['asignaciones' => DistributionMessages::ROOM_FULL]);
            }
        }
    }
}
