<?php

namespace App\Services\RiskCenter\Academic;

use App\Models\RiskCenter;
use App\Support\Database\AccentInsensitiveSearch;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Consulta de solo lectura de la Central de Riesgo de estudiantes: una fila por reporte aprobado.
 *
 * El registro es institucional: no se filtra por docente ni por examen. No hay operaciones de
 * escritura; la rectificación es otra historia.
 */
class RiskCenterQueryService
{
    private const MAX_ID = 2147483647;

    /** Palabras de la búsqueda que se consideran; el resto se ignora para acotar la consulta. */
    private const MAX_TERMS = 5;

    /** Nombre completo tal como lo lee una persona, para que «jose perez» encuentre «José Luis Pérez». */
    private const FULL_NAME = "CONCAT_WS(' ', estudiante.nombre, estudiante.apellido_paterno, "
        . "estudiante.apellido_materno)";

    /**
     * Registros del más reciente al más antiguo. `$search` coincide con el nombre o el código SIS del
     * estudiante, sin distinguir mayúsculas ni tildes; cada palabra debe coincidir.
     */
    public function paginate(?string $search, ?int $faultTypeId, int $page, int $perPage): LengthAwarePaginator
    {
        $query = RiskCenter::query()
            ->with(['student', 'studentReport.incidentType', 'studentReport.exam.subject'])
            ->orderByDesc('fecha_registro')
            ->orderByDesc('id_cr_estudiante');

        if ($faultTypeId !== null) {
            $query->whereHas('studentReport', fn (Builder $report) => $report->where('id_tipo_falta', $faultTypeId));
        }

        $terms = $this->searchTerms($search);

        if ($terms !== []) {
            $query->whereHas('student', function (Builder $student) use ($terms) {
                foreach ($terms as $term) {
                    $this->matchStudent($student, AccentInsensitiveSearch::pattern($term));
                }
            });
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Un registro con todo lo que muestra su detalle.
     *
     * @throws ModelNotFoundException si no existe (un id fuera del rango de la columna tampoco existe)
     */
    public function find(int $id): RiskCenter
    {
        $record = $id < 1 || $id > self::MAX_ID
            ? null
            : RiskCenter::query()
                ->with([
                    'student',
                    'studentReport.incidentType',
                    'studentReport.exam.subject',
                    'studentReport.exam.career',
                    'studentReport.entryAttempt.classroom',
                    'studentReport.userReporter',
                    'studentReport.userReviewer',
                ])
                ->find($id);

        if ($record === null) {
            throw new ModelNotFoundException('No existe el registro de la Central de Riesgo indicado.');
        }

        return $record;
    }

    /**
     * Resumen de los antecedentes de un estudiante, para avisar al controlador de ingreso (HU-032).
     *
     * `latest` es el registro más reciente (fecha_registro y, a igualdad, el de mayor id) o null si no
     * hay ninguno; `total` cuenta todos. Se resuelve en una sola consulta.
     *
     * @return array{
     *     total: int,
     *     latest: array{
     *         id_cr_estudiante: int,
     *         tipo_falta: array{id_falta: int, nombre: string},
     *         fecha_registro: \Carbon\Carbon
     *     }|null
     * }
     */
    public function summaryForStudent(int $studentId): array
    {
        $latest = RiskCenter::query()
            ->join('reporte_estudiante', 'reporte_estudiante.id_reporte_est', '=', 'central_riesgo.id_reporte_est')
            ->join('tipo_falta', 'tipo_falta.id_falta', '=', 'reporte_estudiante.id_tipo_falta')
            ->where('central_riesgo.id_estudiante', $studentId)
            ->select('central_riesgo.*', 'tipo_falta.id_falta', 'tipo_falta.nombre as tipo_falta_nombre')
            ->selectRaw('count(*) over () as total')
            ->orderByDesc('central_riesgo.fecha_registro')
            ->orderByDesc('central_riesgo.id_cr_estudiante')
            ->first();

        if ($latest === null) {
            return ['total' => 0, 'latest' => null];
        }

        return [
            'total' => (int) $latest->total,
            'latest' => [
                'id_cr_estudiante' => (int) $latest->id_cr_estudiante,
                'tipo_falta' => [
                    'id_falta' => (int) $latest->id_falta,
                    'nombre' => $latest->tipo_falta_nombre,
                ],
                'fecha_registro' => $latest->fecha_registro,
            ],
        ];
    }

    /** @return array<int, string> */
    private function searchTerms(?string $search): array
    {
        $normalized = AccentInsensitiveSearch::normalize((string) $search);
        $words = preg_split('/\s+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_slice($words, 0, self::MAX_TERMS);
    }

    private function matchStudent(Builder $student, string $pattern): void
    {
        $student->where(function (Builder $match) use ($pattern) {
            $match->whereRaw(AccentInsensitiveSearch::condition(self::FULL_NAME), [$pattern])
                ->orWhereRaw(AccentInsensitiveSearch::condition('estudiante.cod_sis'), [$pattern]);
        });
    }
}
