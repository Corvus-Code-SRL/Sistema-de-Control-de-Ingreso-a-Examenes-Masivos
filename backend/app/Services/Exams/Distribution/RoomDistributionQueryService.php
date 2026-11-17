<?php

namespace App\Services\Exams\Distribution;

use App\Models\Exam;
use App\Services\Exams\ExamCollaboratorService;
use App\Support\Exams\DistributionMessages;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Consultas y propuestas sin persistencia para la preparación de ambientes.
 *
 * @phpstan-import-type DistributionStudent from DistributionRosterQuery
 * @phpstan-type StudentView array{id_estudiante:int, codigo_sis:string, nombre_completo:string, id_ambiente:?int}
 * @phpstan-type ExamOption array{id_examen:int, nombre:string, materia:?array{nombre:string}, fecha:string,
 *     hora_inicio:string, cantidad_ambientes:int, es_creador:bool, editable:bool}
 * @phpstan-type DistributionView array{examen:ExamOption, editable:bool, motivo_solo_lectura:?string,
 *     version:string, total_habilitados:int, total_no_habilitados:int, capacidad_total:int, faltan_asientos:int,
 *     ambientes:list<array{id_ambiente:int, nro_aula:string, capacidad:int, asignados:int}>,
 *     estudiantes:list<StudentView>}
 * @phpstan-type DistributionProposal array{estudiantes:list<StudentView>, sin_asignar:int}
 */
class RoomDistributionQueryService
{
    public function __construct(
        private RoomDistributionAccess $access,
        private ExamCollaboratorService $collaborators,
        private DistributionRosterQuery $roster,
        private DistributionReconciler $reconciler,
        private SurnameDistributor $distributor,
        private DistributionAssignmentValidator $validator
    ) {
    }

    /** @return list<ExamOption> */
    public function exams(string $teacherId): array
    {
        $this->access->assertTeacher($teacherId);

        return Exam::query()->with('subject')->withCount('classrooms')
            ->where('estado', Exam::PROGRAMADO)
            ->where(fn (Builder $query): Builder => $this->collaborators->whereTaughtBy($query, $teacherId))
            ->orderBy('fecha')->orderBy('hora_inicio')->orderBy('id_examen')->get()
            ->map(fn (Exam $exam): array => $this->examOption($exam, $teacherId, (int) $exam->classrooms_count))
            ->all();
    }

    /** @return DistributionView */
    public function current(int $examId, string $teacherId): array
    {
        $exam = $this->access->forRead($examId, $teacherId);
        $students = $this->roster->eligibleStudents($examId);
        $rooms = $this->roster->rooms($examId);
        $saved = $this->roster->savedRows($examId);
        $assignments = $this->reconciler->reconcile(array_column($students, 'id_estudiante'), $rooms, $saved);

        if (count($rooms) === 1) {
            $assignments = $this->distributor->distribute($students, $rooms)['assignments'];
        }

        $capacity = array_sum(array_column($rooms, 'capacidad'));
        $missingSeats = max(0, count($students) - $capacity);
        $reason = $this->access->readOnlyReason($exam, $teacherId);

        if ($reason === null && count($rooms) < DistributionAssignmentValidator::MIN_ROOMS) {
            if ($rooms === []) {
                $reason = DistributionMessages::NO_ROOMS;
            } elseif ($missingSeats > 0) {
                $reason = sprintf(
                    DistributionMessages::SINGLE_ROOM_INSUFFICIENT,
                    $rooms[0]['nro_aula'], $capacity, count($students), $missingSeats
                );
            } else {
                $reason = sprintf(DistributionMessages::SINGLE_ROOM, $rooms[0]['nro_aula']);
            }
        }

        $occupancy = array_count_values(array_filter($assignments, fn (?int $roomId): bool => $roomId !== null));

        return [
            'examen' => $this->examOption($exam, $teacherId, count($rooms)),
            'editable' => $reason === null,
            'motivo_solo_lectura' => $reason,
            'version' => DistributionVersion::fromRows($saved),
            'total_habilitados' => count($students),
            'total_no_habilitados' => $this->roster->ineligibleCount($examId),
            'capacidad_total' => $capacity,
            'faltan_asientos' => $missingSeats,
            'ambientes' => array_map(fn (array $room): array => $room + [
                'asignados' => $occupancy[$room['id_ambiente']] ?? 0,
            ], $rooms),
            'estudiantes' => $this->studentViews($students, $assignments),
        ];
    }

    /** @return DistributionProposal */
    public function proposal(int $examId, string $teacherId): array
    {
        return DB::transaction(function () use ($examId, $teacherId): array {
            $this->access->lockForProposal($examId, $teacherId);
            $rooms = $this->roster->rooms($examId);
            $this->validator->assertMultipleRooms($rooms);
            $students = $this->roster->eligibleStudents($examId);
            $proposal = $this->distributor->distribute($students, $rooms);

            return [
                'estudiantes' => $this->studentViews($students, $proposal['assignments']),
                'sin_asignar' => count($proposal['unassigned']),
            ];
        });
    }

    /** @return ExamOption */
    private function examOption(Exam $exam, string $teacherId, int $roomCount): array
    {
        return [
            'id_examen' => (int) $exam->id_examen,
            'nombre' => (string) $exam->nombre_examen,
            'materia' => $exam->subject === null ? null : ['nombre' => (string) $exam->subject->nombre],
            'fecha' => $exam->fecha->format('Y-m-d'),
            'hora_inicio' => (string) $exam->hora_inicio,
            'cantidad_ambientes' => $roomCount,
            'es_creador' => (string) $exam->id_usuario_docente === $teacherId,
            'editable' => $this->access->readOnlyReason($exam, $teacherId) === null
                && $roomCount >= DistributionAssignmentValidator::MIN_ROOMS,
        ];
    }

    /**
     * @param list<DistributionStudent> $students
     * @param array<int, int|null> $assignments
     * @return list<StudentView>
     */
    private function studentViews(array $students, array $assignments): array
    {
        return array_map(fn (array $student): array => [
            'id_estudiante' => $student['id_estudiante'],
            'codigo_sis' => $student['cod_sis'],
            'nombre_completo' => implode(' ', array_filter([
                $student['apellido_paterno'], $student['apellido_materno'] ?? '', $student['nombre'],
            ], fn (string $part): bool => $part !== '')),
            'id_ambiente' => $assignments[$student['id_estudiante']] ?? null,
        ], $students);
    }
}
