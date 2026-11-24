<?php

namespace App\Services\Exams\Distribution;

use App\Exceptions\Exams\StaleDistributionException;
use App\Models\RoomDistribution;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Revalida y reemplaza la planificación completa junto con su bitácora, dentro de una transacción.
 *
 * @phpstan-import-type SavedAssignment from DistributionRosterQuery
 * @phpstan-import-type DistributionView from RoomDistributionQueryService
 */
class RoomDistributionSaveService
{
    public function __construct(
        private RoomDistributionAccess $access,
        private DistributionRosterQuery $roster,
        private DistributionAssignmentValidator $validator,
        private DistributionAudit $audit,
        private RoomDistributionQueryService $query
    ) {
    }

    /**
     * Con cero habilitados se acepta una lista vacía: limpia las filas previas y audita la confirmación.
     * No se persiste un marcador adicional; la distribución vacía tiene la versión inicial.
     *
     * @param list<array{id_estudiante:int, id_ambiente:int}> $assignments
     * @return DistributionView
     */
    public function save(int $examId, string $version, array $assignments, string $teacherId): array
    {
        return DB::transaction(function () use ($examId, $version, $assignments, $teacherId): array {
            $this->access->lockForWrite($examId, $teacherId);
            $this->roster->lockForSave($examId);
            $before = $this->roster->savedRows($examId, true);

            if (! hash_equals(DistributionVersion::fromRows($before), $version)) {
                throw new StaleDistributionException();
            }

            $students = $this->roster->eligibleStudents($examId);
            $rooms = $this->roster->rooms($examId);
            $this->validator->validate($students, $rooms, $assignments);
            $assignedAt = $this->assignmentTime($before);
            $rows = array_map(fn (array $assignment): array => [
                'id_examen' => $examId,
                'id_estudiante' => $assignment['id_estudiante'],
                'id_ambiente' => $assignment['id_ambiente'],
                'id_usuario_asignador' => $teacherId,
                'asignado_en' => $assignedAt,
            ], $assignments);

            // No se usa save()/destroy(): Eloquent no resuelve la clave primaria compuesta.
            RoomDistribution::query()->where('id_examen', $examId)->delete();

            if ($rows !== []) {
                RoomDistribution::query()->insert($rows);
            }

            $after = $this->roster->savedRows($examId);
            $this->audit->record($examId, $before, $after, $teacherId);

            return $this->query->current($examId, $teacherId);
        });
    }

    /** @param list<SavedAssignment> $before */
    private function assignmentTime(array $before): string
    {
        $timestamp = Carbon::now('UTC');

        // Incluso con reloj congelado o dos llamadas en el mismo microsegundo se invalida la versión anterior.
        foreach ($before as $row) {
            $previous = Carbon::parse($row['asignado_en']);

            if ($timestamp->lessThanOrEqualTo($previous)) {
                $timestamp = $previous->addMicrosecond();
            }
        }

        return $timestamp->format('Y-m-d H:i:s.uP');
    }
}
