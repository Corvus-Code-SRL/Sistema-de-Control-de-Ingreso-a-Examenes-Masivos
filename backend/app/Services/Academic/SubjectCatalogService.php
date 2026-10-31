<?php

namespace App\Services\Academic;

use App\Exceptions\Academic\ActivePeriodNotConfiguredException;
use App\Models\AcademicOffering;
use App\Models\Group;
use App\Models\Period;
use App\Models\SubjectCareer;
use App\Support\RecordStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

/**
 * Consulta el catálogo institucional de materias como pares materia-carrera.
 *
 * El docente no tiene relación directa con la materia: su vínculo se deriva de los
 * grupos que dicta en el periodo activo. Solo ve los pares que la oferta académica del
 * periodo activo (oferta_academica) incluye: una materia que no se dicta ese periodo no
 * aparece, aunque siga en el catálogo, y puede ofertarse de nuevo después.
 */
class SubjectCatalogService
{
    public function listSubjectCareers(string $teacherId): array
    {
        $periodId = $this->activePeriodId();

        $pairs = $this->basePairQuery($teacherId, $periodId)
            ->whereExists($this->activeOfferingConstraint($periodId))
            ->orderBy('materia.nombre')
            ->orderBy('carrera.nombre')
            ->get();

        return [
            'pairs' => $pairs,
            'meta' => [
                'total' => $pairs->count(),
                'total_mias' => $pairs->where('cantidad_grupos', '>', 0)->count(),
                'id_periodo_activo' => $periodId,
                'nombre_periodo_activo' => $this->periodLabel($periodId),
            ],
            'mensaje' => $pairs->isEmpty()
                ? 'No hay materias disponibles en el catálogo institucional.'
                : null,
        ];
    }

    /**
     * Resuelve el par que el docente fija como contexto de trabajo: debe existir y estar activo.
     */
    public function findSelectablePair(int $careerId, int $subjectId, string $teacherId): SubjectCareer
    {
        $pair = $this->findPairOrFail($careerId, $subjectId, $teacherId);

        $this->assertPairIsSelectable($pair);

        return $pair;
    }

    /**
     * Resuelve el par que el docente consulta en el período activo: además de existir y estar
     * activo, debe estar ofertado en él. Un par no ofertado responde como inexistente (404).
     */
    public function findOfferedPair(int $careerId, int $subjectId, string $teacherId): SubjectCareer
    {
        $pair = $this->findSelectablePair($careerId, $subjectId, $teacherId);

        $isOffered = AcademicOffering::query()
            ->where('id_carrera', $careerId)
            ->where('id_materia', $subjectId)
            ->where('id_gestion_periodo', $this->activePeriodId())
            ->where('estado', RecordStatus::ACTIVE)
            ->exists();

        if (! $isOffered) {
            throw new ModelNotFoundException(
                'La materia no se ofrece en el período académico activo.'
            );
        }

        return $pair;
    }

    /**
     * Id del período vigente (gestion_periodo.vigente). Sin período vigente falla con
     * ActivePeriodNotConfiguredException, que responde un 500 con la instrucción de fijarlo.
     */
    public function activePeriodId(): int
    {
        $id = Period::query()->current()->value('id_gestion_periodo');

        if ($id === null) {
            throw new ActivePeriodNotConfiguredException();
        }

        return (int) $id;
    }

    /** Etiqueta del período vigente («1/2026», «3/2026»). */
    public function activePeriodName(): ?string
    {
        return $this->periodLabel($this->activePeriodId());
    }

    private function periodLabel(int $periodId): ?string
    {
        return Period::query()->withLabel()->find($periodId)?->label;
    }

    /** Subconsulta correlacionada: el par de la fila tiene una oferta ACTIVA en el período dado. */
    private function activeOfferingConstraint(int $periodId): \Closure
    {
        return function ($query) use ($periodId): void {
            $query->selectRaw('1')
                ->from('oferta_academica')
                ->whereColumn('oferta_academica.id_carrera', 'materia_carrera.id_carrera')
                ->whereColumn('oferta_academica.id_materia', 'materia_carrera.id_materia')
                ->where('oferta_academica.id_gestion_periodo', $periodId)
                ->where('oferta_academica.estado', RecordStatus::ACTIVE);
        };
    }

    private function basePairQuery(string $teacherId, ?int $periodId = null): Builder
    {
        return SubjectCareer::query()
            ->join('materia', 'materia.id_materia', '=', 'materia_carrera.id_materia')
            ->join('carrera', 'carrera.id_carrera', '=', 'materia_carrera.id_carrera')
            ->select('materia_carrera.*')
            ->selectSub($this->teacherGroupCountQuery($teacherId, $periodId ?? $this->activePeriodId()), 'cantidad_grupos')
            ->with(['subject', 'career']);
    }

    /**
     * Subconsulta correlacionada: cuántos grupos dicta el docente en el par de cada fila.
     * Resuelve "mis materias" sin lanzar una consulta por registro.
     */
    private function teacherGroupCountQuery(string $teacherId, int $periodId): Builder
    {
        return Group::query()
            ->selectRaw('count(*)')
            ->whereColumn('grupo.id_carrera', 'materia_carrera.id_carrera')
            ->whereColumn('grupo.id_materia', 'materia_carrera.id_materia')
            ->where('grupo.id_usuario_docente', $teacherId)
            ->where('grupo.id_gestion_periodo', $periodId)
            ->where('grupo.estado', RecordStatus::ACTIVE);
    }

    private function findPairOrFail(int $careerId, int $subjectId, string $teacherId): SubjectCareer
    {
        $pair = $this->basePairQuery($teacherId)
            ->where('materia_carrera.id_carrera', $careerId)
            ->where('materia_carrera.id_materia', $subjectId)
            ->first();

        if ($pair === null) {
            throw new ModelNotFoundException(
                'No existe la materia indicada dentro de esa carrera.'
            );
        }

        return $pair;
    }

    /**
     * El estado vive tanto en materia como en materia_carrera: basta que uno de los dos
     * esté INACTIVO para que el par no pueda establecerse como contexto de trabajo.
     */
    private function assertPairIsSelectable(SubjectCareer $pair): void
    {
        if ($pair->estado === RecordStatus::ACTIVE && $pair->subject->estado === RecordStatus::ACTIVE) {
            return;
        }

        throw ValidationException::withMessages([
            'id_materia' => [
                'La materia seleccionada no está activa en esta carrera '
                . 'y no puede establecerse como contexto de trabajo.',
            ],
        ]);
    }
}
