<?php

namespace App\Services\RiskCenter\Academic;

use App\Models\RiskCenter;
use App\Models\Student;
use App\Support\Database\AccentInsensitiveSearch;
use App\Support\SisCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Consultas de la vista «Rectificar registro de la central» (HU-062): búsqueda de estudiantes, sus registros
 * activos, sus registros ya rectificados (aparte) y la ficha completa de un registro activo.
 *
 * Solo lectura. Es independiente de RiskCenterQueryService (HU-046), que nunca devuelve rectificados. Aquí los
 * rectificados se piden a propósito con RiskCenter::withVoided(). Cada registro activo trae además el resumen de
 * sus solicitudes de rectificación (atributo `resumen_rectificacion`).
 */
class RiskCenterRectificationQueryService
{
    /** Coincidencias que se devuelven; si hay más, el cliente pide afinar la búsqueda. */
    public const STUDENT_LIMIT = 10;

    /** Palabras de la búsqueda por nombre que se consideran, para acotar la consulta. */
    private const MAX_TERMS = 5;

    private const FULL_NAME = "CONCAT_WS(' ', nombre, apellido_paterno, apellido_materno)";

    private RectificationRequestQueryService $requests;

    public function __construct(RectificationRequestQueryService $requests)
    {
        $this->requests = $requests;
    }

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
     * Estudiante con sus registros activos (del más reciente al más antiguo) y, aparte, los que ya se
     * rectificaron (el más recientemente rectificado primero).
     *
     * @return array{
     *     estudiante: Student,
     *     registros: Collection<int, RiskCenter>,
     *     rectificados: Collection<int, RiskCenter>
     * }
     *
     * @throws ModelNotFoundException
     */
    public function recordsOfStudent(int $studentId): array
    {
        $student = Student::query()->find($studentId);

        if ($student === null) {
            throw new ModelNotFoundException('No existe el estudiante indicado.');
        }

        $summaryRelations = [
            'studentReport.incidentType',
            'studentReport.exam.subject',
            'studentReport.userReviewer',
        ];

        $active = RiskCenter::query()
            ->where('id_estudiante', $studentId)
            ->with($summaryRelations)
            ->orderByDesc('fecha_registro')
            ->orderByDesc('id_cr_estudiante')
            ->get();

        $this->requests->attachSummaries($active);

        $voided = RiskCenter::withVoided()
            ->where('id_estudiante', $studentId)
            ->whereNotNull('rectificado_en')
            ->with(array_merge($summaryRelations, ['rectifier']))
            ->orderByDesc('rectificado_en')
            ->orderByDesc('id_cr_estudiante')
            ->get();

        return ['estudiante' => $student, 'registros' => $active, 'rectificados' => $voided];
    }

    /**
     * Ficha completa de un registro activo. Uno rectificado ya no está en la Central: se trata como inexistente.
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
                'studentReport.incidentRoom',
                'studentReport.userReporter',
                'studentReport.userReviewer',
            ])
            ->find($id);

        if ($record === null) {
            throw new ModelNotFoundException('No existe el registro de la Central de Riesgo indicado.');
        }

        $this->requests->attachSummaries(new Collection([$record]));

        return $record;
    }

    /**
     * Un registro ya rectificado con lo que muestra la lista «Registros rectificados».
     *
     * @throws ModelNotFoundException
     */
    public function findRectifiedRecord(int $id): RiskCenter
    {
        $record = RiskCenter::withVoided()
            ->whereNotNull('rectificado_en')
            ->with([
                'studentReport.incidentType',
                'studentReport.exam.subject',
                'studentReport.userReviewer',
                'rectifier',
            ])
            ->find($id);

        if ($record === null) {
            throw new ModelNotFoundException('No existe el registro de la Central de Riesgo indicado.');
        }

        return $record;
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
