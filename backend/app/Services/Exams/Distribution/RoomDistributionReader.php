<?php

namespace App\Services\Exams\Distribution;

use App\Models\Exam;

/** Lee la planificación vigente para el ingreso; nunca la modifica ni reparte a quien no tenga ambiente. */
class RoomDistributionReader
{
    public function __construct(private DistributionRosterQuery $roster, private DistributionReconciler $reconciler)
    {
    }

    public function isSuspended(int $examId): bool
    {
        return Exam::query()->whereKey($examId)->whereNotNull('distribucion_suspendida_en')->exists();
    }

    /**
     * Ambiente planificado de cada habilitado. Sin distribución, o suspendida, el mapa queda vacío:
     * el estudiante puede ingresar por cualquier ambiente del examen.
     *
     * @return array<int, array{id_ambiente:int, nro_aula:string}>
     */
    public function assignedRooms(int $examId): array
    {
        $saved = $this->isSuspended($examId) ? [] : $this->roster->savedRows($examId);

        if ($saved === []) {
            return [];
        }

        $rooms = $this->roster->rooms($examId);
        $names = array_column($rooms, 'nro_aula', 'id_ambiente');
        $studentIds = array_column($this->roster->eligibleStudents($examId), 'id_estudiante');
        $assigned = [];

        foreach ($this->reconciler->reconcile($studentIds, $rooms, $saved) as $studentId => $roomId) {
            if ($roomId !== null) {
                $assigned[$studentId] = ['id_ambiente' => $roomId, 'nro_aula' => $names[$roomId]];
            }
        }

        return $assigned;
    }
}
