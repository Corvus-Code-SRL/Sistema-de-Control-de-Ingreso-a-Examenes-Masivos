<?php

namespace App\Services\Exams\Distribution;

/** Conserva solo asignaciones vigentes; los habilitados nuevos quedan sin ambiente en edición. */
class DistributionReconciler
{
    /**
     * Si una capacidad disminuye, conserva primero los estudiantes en el orden de la nómina recibida.
     *
     * @param list<int> $eligibleStudentIds
     * @param list<array{id_ambiente:int, capacidad:int}> $rooms
     * @param list<array{id_estudiante:int, id_ambiente:int}> $savedAssignments
     * @return array<int, int|null>
     */
    public function reconcile(array $eligibleStudentIds, array $rooms, array $savedAssignments): array
    {
        $remaining = array_column($rooms, 'capacidad', 'id_ambiente');
        $savedRooms = array_column($savedAssignments, 'id_ambiente', 'id_estudiante');
        $assignments = [];

        foreach ($eligibleStudentIds as $studentId) {
            $roomId = $savedRooms[$studentId] ?? null;

            if ($roomId !== null && ($remaining[$roomId] ?? 0) > 0) {
                $assignments[$studentId] = $roomId;
                $remaining[$roomId]--;
            } else {
                $assignments[$studentId] = null;
            }
        }

        return $assignments;
    }
}
