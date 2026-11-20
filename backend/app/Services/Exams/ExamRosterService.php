<?php

namespace App\Services\Exams;

use App\Models\Exam;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Fuente única de la nómina de un examen (HU-023, HU-028, HU-029, HU-031, HU-032, HU-038).
 *
 * expected(): estudiantes esperados = grupo_estudiante ⋈ grupo_examen más los estudiantes excepcionales
 * de ese examen (examen_estudiante con es_excepcional), con LEFT JOIN a examen_estudiante. Sin fila →
 * estado_habilitacion = HABILITADO, estado_ingreso = NO_INGRESO. En FINALIZADO lee solo de
 * examen_estudiante (foto congelada, que ya incluye a los excepcionales).
 *
 * expectedTotals() / enteredTotals(): los mismos conteos, por lote, para el listado del control de ingreso.
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

        return DB::query()->fromSub(
            $this->rosterExpected($examId)->unionAll($this->exceptionalExpected($examId)),
            'estudiante'
        );
    }

    /**
     * Cantidad de esperados por examen (nómina de sus grupos más excepcionales), sin contar dos veces a
     * quien figura en varios grupos.
     *
     * @param array<int, int> $examIds
     * @return array<int, int> id_examen => total
     */
    public function expectedTotals(array $examIds): array
    {
        $rows = DB::query()->fromSub(
            DB::table('grupo_examen as gx')
                ->join('grupo_estudiante as gs', 'gs.id_grupo', '=', 'gx.id_grupo')
                ->whereIn('gx.id_examen', $examIds)
                ->select('gx.id_examen', 'gs.id_estudiante')
                ->union(
                    DB::table('examen_estudiante as ee')
                        ->whereIn('ee.id_examen', $examIds)
                        ->where('ee.es_excepcional', true)
                        ->select('ee.id_examen', 'ee.id_estudiante')
                ),
            'esperados'
        )
            ->selectRaw('id_examen, count(*) as total')
            ->groupBy('id_examen')
            ->pluck('total', 'id_examen');

        return $rows->map(fn ($total): int => (int) $total)->all();
    }

    /**
     * Cantidad de esperados que ya ingresaron (INGRESO o CON_RETRASO) por examen.
     *
     * @param array<int, int> $examIds
     * @return array<int, int> id_examen => total
     */
    public function enteredTotals(array $examIds): array
    {
        $rows = DB::table('examen_estudiante as ee')
            ->whereIn('ee.id_examen', $examIds)
            ->whereIn('ee.estado_ingreso', ['INGRESO', 'CON_RETRASO'])
            ->where(function ($query): void {
                $query->where('ee.es_excepcional', true)
                    ->orWhereExists(function ($roster): void {
                        $roster->selectRaw('1')
                            ->from('grupo_examen as gx')
                            ->join('grupo_estudiante as gs', 'gs.id_grupo', '=', 'gx.id_grupo')
                            ->whereColumn('gx.id_examen', 'ee.id_examen')
                            ->whereColumn('gs.id_estudiante', 'ee.id_estudiante');
                    });
            })
            ->selectRaw('ee.id_examen, count(*) as total')
            ->groupBy('ee.id_examen')
            ->pluck('total', 'id_examen');

        return $rows->map(fn ($total): int => (int) $total)->all();
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

    private function rosterExpected(int $examId): Builder
    {
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
            ->selectRaw('false AS es_excepcional')
            ->selectRaw('NULL::varchar(255) AS motivo_excepcional')
            ->groupBy(
                'estudiante.id_estudiante',
                'examen_estudiante.estado_habilitacion',
                'examen_estudiante.estado_ingreso',
                'examen_estudiante.observacion',
                'examen_estudiante.hora_ingreso',
                'examen_estudiante.id_ambiente'
            );
    }

    /** Excepcionales del examen que no figuran ya en la nómina de sus grupos (mismas columnas que la nómina). */
    private function exceptionalExpected(int $examId): Builder
    {
        return DB::table('estudiante')
            ->join('examen_estudiante as ee', function ($join) use ($examId): void {
                $join->on('ee.id_estudiante', '=', 'estudiante.id_estudiante')
                    ->where('ee.id_examen', '=', $examId)
                    ->where('ee.es_excepcional', '=', true);
            })
            ->join('grupo', 'grupo.id_grupo', '=', 'ee.id_grupo_excepcional')
            ->whereNotExists(function (Builder $query) use ($examId): void {
                $query->selectRaw('1')
                    ->from('grupo_estudiante as ge')
                    ->join('grupo_examen as gx', 'gx.id_grupo', '=', 'ge.id_grupo')
                    ->where('gx.id_examen', $examId)
                    ->whereColumn('ge.id_estudiante', 'estudiante.id_estudiante');
            })
            ->select('estudiante.*')
            ->selectRaw('ee.id_grupo_excepcional AS id_grupo')
            ->selectRaw('grupo.num_grupo AS num_grupo')
            ->selectRaw('ee.estado_habilitacion AS estado_habilitacion')
            ->selectRaw('ee.estado_ingreso AS estado_ingreso')
            ->selectRaw('ee.observacion AS observacion')
            ->selectRaw('ee.hora_ingreso AS hora_ingreso')
            ->selectRaw('ee.id_ambiente AS id_ambiente')
            ->selectRaw('true AS es_excepcional')
            ->selectRaw('ee.motivo_excepcional AS motivo_excepcional');
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
            ->join('grupo', 'grupo.id_grupo', '=', 'examen_estudiante.id_grupo_efectivo')
            ->where('examen_estudiante.id_examen', $examId)
            ->select(
                'estudiante.*',
                'examen_estudiante.id_grupo_efectivo as id_grupo',
                'grupo.num_grupo',
                'examen_estudiante.estado_habilitacion',
                'examen_estudiante.estado_ingreso',
                'examen_estudiante.observacion',
                'examen_estudiante.hora_ingreso',
                'examen_estudiante.id_ambiente',
                'examen_estudiante.es_excepcional',
                'examen_estudiante.motivo_excepcional'
            );
    }
}
