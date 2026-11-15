<?php

namespace App\Services\RiskCenter\Academic;

use App\Models\IncidentType;
use App\Models\RiskCenter;
use App\Models\Student;
use App\Support\Database\AccentInsensitiveSearch;
use App\Support\SisCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Consultas de la vista «Rectificar registro de la central» (HU-062): búsqueda de estudiantes, sus
 * registros confirmados con su estado de rectificación y el detalle con el historial de versiones.
 *
 * Solo lectura. Es independiente de RiskCenterQueryService (HU-046): esa consulta no expone el estado
 * de rectificación.
 */
class RiskCenterRectificationQueryService
{
    /** Coincidencias que se devuelven; si hay más, el cliente pide afinar la búsqueda. */
    public const STUDENT_LIMIT = 10;

    /** Palabras de la búsqueda por nombre que se consideran, para acotar la consulta. */
    private const MAX_TERMS = 5;

    private const FULL_NAME = "CONCAT_WS(' ', nombre, apellido_paterno, apellido_materno)";

    /**
     * Estudiantes que coinciden con el dato: el código SIS o el carnet exactos o, si no hay ninguno,
     * el nombre (sin distinguir mayúsculas ni tildes; cada palabra puede ser parcial).
     *
     * @return array{estudiantes: Collection<int, Student>, hay_mas: bool}
     */
    public function searchStudents(string $term): array
    {
        $exact = SisCode::normalize($term);

        $students = $this->limited(
            Student::query()->where(function (Builder $query) use ($exact) {
                $query->where('cod_sis', $exact)->orWhereRaw('upper(btrim(ci)) = ?', [$exact]);
            })
        );

        if ($students->isEmpty() && preg_match('/\p{L}/u', $term) === 1) {
            $students = $this->limited($this->byName($term));
        }

        $hasMore = $students->count() > self::STUDENT_LIMIT;

        return [
            'estudiantes' => $hasMore ? $students->take(self::STUDENT_LIMIT)->values() : $students,
            'hay_mas' => $hasMore,
        ];
    }

    /**
     * Estudiante con sus registros confirmados, del más reciente al más antiguo.
     *
     * @return array{estudiante: Student, registros: Collection<int, RiskCenter>}
     *
     * @throws ModelNotFoundException
     */
    public function recordsOfStudent(int $studentId): array
    {
        $student = Student::query()->find($studentId);

        if ($student === null) {
            throw new ModelNotFoundException('No existe el estudiante indicado.');
        }

        $records = RiskCenter::query()
            ->where('id_estudiante', $studentId)
            ->with([
                'studentReport.incidentType',
                'studentReport.exam.subject',
                'studentReport.userReviewer',
                'currentVersion.incidentType',
            ])
            ->withCount('versions')
            ->orderByDesc('fecha_registro')
            ->orderByDesc('id_cr_estudiante')
            ->get();

        return ['estudiante' => $student, 'registros' => $records];
    }

    /**
     * Un registro con lo fijo, el estado vigente y el historial completo (la versión más nueva primero).
     *
     * @throws ModelNotFoundException
     */
    public function findRecord(int $id): RiskCenter
    {
        $record = RiskCenter::query()
            ->with([
                'student',
                'studentReport.incidentType',
                'studentReport.exam.subject',
                'studentReport.exam.career',
                'studentReport.entryAttempt.classroom',
                'studentReport.userReporter',
                'studentReport.userReviewer',
                'currentVersion.incidentType',
                'versions' => fn ($versions) => $versions->orderByDesc('numero_version'),
                'versions.incidentType',
                'versions.author',
            ])
            ->withCount('versions')
            ->find($id);

        if ($record === null) {
            throw new ModelNotFoundException('No existe el registro de la Central de Riesgo indicado.');
        }

        return $record;
    }

    /** Catálogo de tipos de falta para el selector de la rectificación. */
    public function faultTypes(): Collection
    {
        return IncidentType::query()->orderBy('nombre')->orderBy('id_falta')->get();
    }

    private function byName(string $term): Builder
    {
        $words = preg_split('/\s+/u', AccentInsensitiveSearch::normalize($term), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $query = Student::query();

        foreach (array_slice($words, 0, self::MAX_TERMS) as $word) {
            $query->whereRaw(
                AccentInsensitiveSearch::condition(self::FULL_NAME),
                [AccentInsensitiveSearch::pattern($word)]
            );
        }

        return $query;
    }

    /** @return Collection<int, Student> uno más que el límite, para saber si hay más coincidencias */
    private function limited(Builder $query): Collection
    {
        return $query
            ->orderBy('apellido_paterno')
            ->orderBy('apellido_materno')
            ->orderBy('nombre')
            ->orderBy('id_estudiante')
            ->limit(self::STUDENT_LIMIT + 1)
            ->get();
    }
}
