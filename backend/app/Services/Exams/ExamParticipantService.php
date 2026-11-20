<?php

namespace App\Services\Exams;

use App\Models\Exam;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Participantes de un examen, derivados y nunca copiados.
 *
 * esperados = estudiantes asociados al examen (nómina de sus grupos y excepcionales, HU-023).
 * ingresados = estudiantes con INGRESO o CON_RETRASO.
 * pendientes = estudiantes esperados sin ingreso real.
 */
class ExamParticipantService
{
    private ExamRosterService $roster;

    public function __construct(ExamRosterService $roster)
    {
        $this->roster = $roster;
    }

    /**
     * Un estudiante inscrito en varios grupos del examen aparece una sola vez.
     * Su id_grupo corresponde al menor de esos grupos.
     */
    public function expected(int $examId): Builder
    {
        return $this->roster->expected($examId);
    }

    public function entered(int $examId): Builder
    {
        return DB::table('examen_estudiante')
            ->join('estudiante', 'estudiante.id_estudiante', '=', 'examen_estudiante.id_estudiante')
            ->where('examen_estudiante.id_examen', $examId)
            ->whereIn('examen_estudiante.estado_ingreso', ['INGRESO', 'CON_RETRASO'])
            ->select(
                'estudiante.*',
                'examen_estudiante.id_grupo_efectivo as id_grupo',
                'examen_estudiante.estado_habilitacion',
                'examen_estudiante.estado_ingreso',
                'examen_estudiante.hora_ingreso'
            );
    }

    public function pending(int $examId): Builder
    {
        return $this->expected($examId)->whereNotExists(
            static function (Builder $query) use ($examId): void {
                $query->from('examen_estudiante')
                    ->selectRaw('1')
                    ->whereColumn('examen_estudiante.id_estudiante', 'estudiante.id_estudiante')
                    ->where('examen_estudiante.id_examen', $examId)
                    ->whereIn('examen_estudiante.estado_ingreso', ['INGRESO', 'CON_RETRASO']);
            }
        );
    }

    /**
     * @return array{esperados: int, ingresados: int, pendientes: int}
     */
    public function counts(int $examId): array
    {
        return [
            'esperados' => $this->countOf($this->expected($examId)),
            'ingresados' => $this->entered($examId)->count(),
            'pendientes' => $this->countOf($this->pending($examId)),
        ];
    }

    /**
     * Sustituye la cantidad de estudiantes por grupo de los exámenes FINALIZADOS por la
     * congelada en examen_estudiante, con una sola consulta para toda la colección. Los
     * demás exámenes conservan el conteo en vivo.
     *
     * @param iterable<Exam> $exams con la relación groups ya cargada
     */
    public function applyFrozenStudentCounts(iterable $exams): void
    {
        $finalized = [];

        foreach ($exams as $exam) {
            if ($exam->estado === Exam::FINALIZADO && $exam->relationLoaded('groups')) {
                $finalized[(int) $exam->id_examen] = $exam;
            }
        }

        if ($finalized === []) {
            return;
        }

        $frozenCounts = [];

        $rows = DB::table('examen_estudiante')
            ->whereIn('id_examen', array_keys($finalized))
            ->selectRaw('id_examen, id_grupo_efectivo AS id_grupo, COUNT(*) AS total')
            ->groupBy('id_examen', 'id_grupo_efectivo')
            ->get();

        foreach ($rows as $row) {
            $frozenCounts[$row->id_examen . ':' . $row->id_grupo] = (int) $row->total;
        }

        foreach ($finalized as $examId => $exam) {
            foreach ($exam->groups as $group) {
                $group->cantidad_estudiantes = $frozenCounts[$examId . ':' . $group->id_grupo] ?? 0;
            }
        }
    }

    private function countOf(Builder $query): int
    {
        return DB::query()->fromSub($query, 'participantes')->count();
    }
}
