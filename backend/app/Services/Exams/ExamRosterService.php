<?php

namespace App\Services\Exams;

use App\Models\Exam;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Fuente única de la nómina de un examen (HU-028, HU-029, HU-031, HU-032, HU-038).
 *
 * expected(): estudiantes esperados = grupo_estudiante ⋈ grupo_examen con LEFT JOIN a
 * examen_estudiante. Sin fila → estado_habilitacion = HABILITADO, estado_ingreso =
 * NO_INGRESO. En FINALIZADO lee solo de examen_estudiante (foto congelada).
 *
 * ensureRow(): crea la fila si no existe, sin pisar estado_ingreso, hora_ingreso ni
 * id_ambiente de una fila existente.
 */
class ExamRosterService
{
    public function expected(int $examId): Builder
    {
        $exam = Exam::query()->findOrFail($examId);

        if ($exam->estado === Exam::FINALIZADO) {
            return $this->frozenExpected($examId);
        }

        return DB::table('estudiante')
            ->join(
                'grupo_estudiante',
                'grupo_estudiante.id_estudiante',
                '=',
                'estudiante.id_estudiante'
            )
            ->join(
                'grupo_examen',
                'grupo_examen.id_grupo',
                '=',
                'grupo_estudiante.id_grupo'
            )
            ->join('grupo', 'grupo.id_grupo', '=', 'grupo_estudiante.id_grupo')
            ->leftJoin('examen_estudiante', function ($join) use ($examId): void {
                $join->on('examen_estudiante.id_estudiante', '=', 'estudiante.id_estudiante')
                    ->where('examen_estudiante.id_examen', '=', $examId);
            })
            ->where('grupo_examen.id_examen', $examId)
            ->select('estudiante.*')
            ->selectRaw('MIN(grupo_estudiante.id_grupo) AS id_grupo')
            ->selectRaw('MIN(grupo.num_grupo) AS num_grupo')
            ->selectRaw("COALESCE(examen_estudiante.estado_habilitacion, 'HABILITADO') AS estado_habilitacion")
            ->selectRaw("COALESCE(examen_estudiante.estado_ingreso, 'NO_INGRESO') AS estado_ingreso")
            ->selectRaw('examen_estudiante.observacion AS observacion')
            ->selectRaw('examen_estudiante.hora_ingreso AS hora_ingreso')
            ->selectRaw('examen_estudiante.id_ambiente AS id_ambiente')
            ->groupBy(
                'estudiante.id_estudiante',
                'examen_estudiante.estado_habilitacion',
                'examen_estudiante.estado_ingreso',
                'examen_estudiante.observacion',
                'examen_estudiante.hora_ingreso',
                'examen_estudiante.id_ambiente'
            );
    }

    /**
     * Crea la fila en examen_estudiante si no existe. No pisa estado_ingreso,
     * hora_ingreso ni id_ambiente de una fila existente.
     *
     * @return bool true si insertó, false si ya existía
     */
    public function ensureRow(int $examId, int $studentId, int $groupId): bool
    {
        return DB::table('examen_estudiante')->insertOrIgnore([
            'id_examen' => $examId,
            'id_estudiante' => $studentId,
            'id_grupo' => $groupId,
            'estado_habilitacion' => 'HABILITADO',
            'estado_ingreso' => 'NO_INGRESO',
        ]) > 0;
    }

    private function frozenExpected(int $examId): Builder
    {
        return DB::table('estudiante')
            ->join(
                'examen_estudiante',
                'examen_estudiante.id_estudiante',
                '=',
                'estudiante.id_estudiante'
            )
            ->join('grupo', 'grupo.id_grupo', '=', 'examen_estudiante.id_grupo')
            ->where('examen_estudiante.id_examen', $examId)
            ->select(
                'estudiante.*',
                'examen_estudiante.id_grupo',
                'grupo.num_grupo',
                'examen_estudiante.estado_habilitacion',
                'examen_estudiante.estado_ingreso',
                'examen_estudiante.observacion',
                'examen_estudiante.hora_ingreso',
                'examen_estudiante.id_ambiente'
            );
    }
}
