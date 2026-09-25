<?php

namespace App\Services\Exams;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Participantes de un examen, derivados y nunca copiados.
 *
 *   esperados = estudiantes de los grupos del examen en grupo_estudiante
 *   ingresados = filas de examen_estudiante (solo existen desde un ingreso real)
 *   pendientes = esperados que todavía no tienen fila en examen_estudiante
 *
 * Es la única fuente de esta derivación: el control de ingreso y el seguimiento del
 * examen la consumen en lugar de reescribirla.
 */
class ExamParticipantService
{
    /**
     * Un estudiante con inscripción en más de un grupo del examen aparece una sola
     * vez; id_grupo es el menor de esos grupos.
     */
    public function expected(int $examId): Builder
    {
        return DB::table('estudiante')
            ->join('grupo_estudiante', 'grupo_estudiante.id_estudiante', '=', 'estudiante.id_estudiante')
            ->join('grupo_examen', 'grupo_examen.id_grupo', '=', 'grupo_estudiante.id_grupo')
            ->where('grupo_examen.id_examen', $examId)
            ->select('estudiante.*')
            ->selectRaw('min(grupo_estudiante.id_grupo) as id_grupo')
            ->groupBy('estudiante.id_estudiante');
    }

    public function entered(int $examId): Builder
    {
        return DB::table('examen_estudiante')
            ->join('estudiante', 'estudiante.id_estudiante', '=', 'examen_estudiante.id_estudiante')
            ->where('examen_estudiante.id_examen', $examId)
            ->select(
                'estudiante.*',
                'examen_estudiante.id_grupo',
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
                    ->where('examen_estudiante.id_examen', $examId);
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

    private function countOf(Builder $query): int
    {
        return DB::query()->fromSub($query, 'participantes')->count();
    }
}
