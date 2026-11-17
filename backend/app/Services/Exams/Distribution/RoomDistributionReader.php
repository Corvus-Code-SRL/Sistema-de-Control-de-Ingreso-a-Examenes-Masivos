<?php

namespace App\Services\Exams\Distribution;

use Illuminate\Support\Facades\DB;
use stdClass;

/** Resuelve la planificación para ingreso sin modificarla ni reemplazar sus asignaciones vigentes. */
class RoomDistributionReader
{
    private const SPECIAL_STATE = 'CASO ESPECIAL';

    public function __construct(
        private DistributionRosterQuery $roster,
        private DistributionReconciler $reconciler,
        private SurnameDistributor $distributor
    ) {
    }

    /**
     * Null conserva el cálculo anterior; un mapa vacío significa planificación existente sin lugares válidos.
     * Los excedentes quedan ausentes del mapa: ingreso ya contempla ambiente_asignado null.
     *
     * @return array<int, array{id_ambiente:int, nro_aula:string}>|null
     */
    public function assignedRooms(int $examId): ?array
    {
        $saved = $this->roster->savedRows($examId);

        if ($saved === []) {
            return null;
        }

        $rooms = $this->roster->rooms($examId);
        $assigned = $this->exceptionalRooms($examId);
        $students = array_values(array_filter(
            $this->roster->eligibleStudents($examId),
            fn (array $student): bool => !isset($assigned[$student['id_estudiante']])
        ));
        $available = $this->remainingRooms($rooms, array_column($assigned, 'id_ambiente'));
        $reconciled = $this->reconciler->reconcile(array_column($students, 'id_estudiante'), $available, $saved);
        $valid = array_filter($reconciled, fn (?int $roomId): bool => $roomId !== null);
        $pending = array_values(array_filter(
            $students,
            fn (array $student): bool => !isset($valid[$student['id_estudiante']])
        ));
        $freeRooms = $this->remainingRooms($available, array_values($valid));
        $new = $this->distributor->distribute($pending, $freeRooms)['assignments'];
        $roomNames = array_column($rooms, 'nro_aula', 'id_ambiente');

        foreach ($valid + $new as $studentId => $roomId) {
            $assigned[$studentId] = ['id_ambiente' => $roomId, 'nro_aula' => $roomNames[$roomId]];
        }

        return $assigned;
    }

    /** @return array<int, array{id_ambiente:int, nro_aula:string}> */
    private function exceptionalRooms(int $examId): array
    {
        // HU-023 se consulta fuera de expected(): su ambiente operativo prevalece sobre la planificación.
        return DB::table('examen_estudiante as ee')
            ->join('ambiente as a', 'a.id_ambiente', '=', 'ee.id_ambiente')
            ->where('ee.id_examen', $examId)
            ->where('ee.estado_habilitacion', self::SPECIAL_STATE)
            ->orderBy('ee.id_estudiante')
            ->get(['ee.id_estudiante', 'ee.id_ambiente', 'a.nro_aula'])
            ->mapWithKeys(fn (stdClass $row): array => [(int) $row->id_estudiante => [
                'id_ambiente' => (int) $row->id_ambiente,
                'nro_aula' => (string) $row->nro_aula,
            ]])->all();
    }

    /**
     * @param list<array{id_ambiente:int, nro_aula:string, capacidad:int}> $rooms
     * @param list<int> $occupiedRoomIds
     * @return list<array{id_ambiente:int, nro_aula:string, capacidad:int}>
     */
    private function remainingRooms(array $rooms, array $occupiedRoomIds): array
    {
        $occupied = array_count_values($occupiedRoomIds);

        return array_map(fn (array $room): array => array_merge($room, [
            'capacidad' => max(0, $room['capacidad'] - ($occupied[$room['id_ambiente']] ?? 0)),
        ]), $rooms);
    }
}
