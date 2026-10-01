<?php

namespace App\Services\EntryControl;

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

            $capacity = (int) DB::table('examen_ambiente as ea')
                ->join('ambiente as a', 'a.id_ambiente', '=', 'ea.id_ambiente')
                ->where('ea.id_examen', $examId)
                ->sum('a.capacidad');

            if ($studentCount === 0 || $capacity === 0) {
                throw new DomainException('El examen necesita nómina y ambientes para abrir el ingreso.');
            }

            if ($capacity < $studentCount) {
                throw new DomainException('La capacidad de los ambientes no alcanza para la nómina.');
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
