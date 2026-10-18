<?php

namespace App\Services\EntryControl;

use App\Exceptions\EntryControl\EntryOpeningException;
use App\Models\Exam;
use DomainException;
use Illuminate\Support\Facades\DB;

class RoomAssignmentService
{
    private EntryControlSnapshotService $snapshots;

    public function __construct(EntryControlSnapshotService $snapshots)
    {
        $this->snapshots = $snapshots;
    }

    /**
     * Valida la capacidad antes de abrir. La nómina queda congelada por el estado
     * EN_INGRESO; las aulas se calculan cuando se verifica al estudiante.
     */
    public function prepare(int $examId): void
    {
        $token = DB::transaction(function () use ($examId): string {
            $exam = Exam::query()->whereKey($examId)->lockForUpdate()->firstOrFail();

            if (! in_array($exam->estado, [Exam::PROGRAMADO, Exam::EN_INGRESO], true)) {
                throw new DomainException('El examen no admite preparación de ambientes.');
            }

            $studentCount = (int) DB::table('grupo_estudiante as gs')
                ->join('grupo_examen as gx', 'gx.id_grupo', '=', 'gs.id_grupo')
                ->where('gx.id_examen', $examId)
                ->distinct()
                ->count('gs.id_estudiante');

            if ($studentCount === 0) {
                throw new EntryOpeningException(
                    EntryOpeningException::MISSING_ROSTER,
                    'El examen no tiene estudiantes en su nómina.'
                );
            }

            $rooms = DB::table('examen_ambiente as ea')
                ->join('ambiente as a', 'a.id_ambiente', '=', 'ea.id_ambiente')
                ->where('ea.id_examen', $examId)
                ->selectRaw('count(*) as room_count, coalesce(sum(a.capacidad), 0) as capacity')
                ->first();

            if ((int) $rooms->room_count === 0) {
                throw new EntryOpeningException(
                    EntryOpeningException::MISSING_ROOMS,
                    'El examen no tiene ambientes asignados.'
                );
            }

            $capacity = (int) $rooms->capacity;
            if ($capacity < $studentCount) {
                throw new EntryOpeningException(
                    EntryOpeningException::INSUFFICIENT_CAPACITY,
                    sprintf('Capacidad %d menor que la nómina de %d.', $capacity, $studentCount)
                );
            }

            $token = $this->snapshots->stage($exam);

            if ($exam->estado === Exam::PROGRAMADO) {
                $exam->update(['estado' => Exam::EN_INGRESO]);
            }

            return $token;
        });

        $this->snapshots->activate($examId, $token);
    }

    /**
     * Ordena estudiantes y aulas por ID. El lugar del estudiante en la nómina
     * determina en qué tramo de capacidades acumuladas cae, sin guardar copias.
     * Solo se llama después de comprobar su pertenencia al examen.
     */
    public function assignedRoomFor(int $examId, int $studentId): ?object
    {
        return $this->snapshots->assignedRoomFor($examId, $studentId);
    }

    public function hasPreparedSnapshot(int $examId): bool
    {
        return $this->snapshots->ready($examId);
    }
}
