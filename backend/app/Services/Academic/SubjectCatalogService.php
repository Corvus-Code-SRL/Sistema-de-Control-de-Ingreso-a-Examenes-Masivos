<?php

namespace App\Services\Academic;

use App\Models\Group;
use App\Models\SubjectCareer;
use App\Support\RecordStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

/**
 * Consulta el catálogo institucional de materias y los grupos de un par materia-carrera.
 *
 * El docente no tiene relación directa con la materia: su vínculo se deriva de los
 * grupos que dicta en el periodo activo.
 */
class SubjectCatalogService
{
    public function listSubjectCareers(): array
    {
        $pairs = $this->basePairQuery()
            ->orderBy('materia.nombre')
            ->orderBy('carrera.nombre')
            ->get();

        return [
            'pairs' => $pairs,
            'meta' => [
                'total' => $pairs->count(),
                'total_mias' => $pairs->where('cantidad_grupos', '>', 0)->count(),
                'id_periodo_activo' => $this->activePeriodId(),
            ],
            'mensaje' => $pairs->isEmpty()
                ? 'No hay materias disponibles en el catálogo institucional.'
                : null,
        ];
    }

    public function listGroupsForPair(int $careerId, int $subjectId): array
    {
        $pair = $this->findPairOrFail($careerId, $subjectId);

        $this->assertPairIsSelectable($pair);

        $groups = $this->markOwnGroups(
            Group::query()
                ->where('id_carrera', $careerId)
                ->where('id_materia', $subjectId)
                ->where('id_periodo', $this->activePeriodId())
                ->with('period')
                ->orderBy('num_grupo')
                ->get()
        );

        return [
            'pair' => $pair,
            'groups' => $groups,
            'meta' => [
                'total' => $groups->count(),
                'total_mios' => $groups->where('es_mio', true)->count(),
                'id_periodo_activo' => $this->activePeriodId(),
            ],
        ];
    }

    public function activePeriodId(): int
    {
        return (int) config('sciem.periodo_activo_id');
    }

    private function teacherId(): string
    {
        return (string) config('sciem.docente_fijo_id');
    }

    private function basePairQuery(): Builder
    {
        return SubjectCareer::query()
            ->join('materia', 'materia.id_materia', '=', 'materia_carrera.id_materia')
            ->join('carrera', 'carrera.id_carrera', '=', 'materia_carrera.id_carrera')
            ->select('materia_carrera.*')
            ->selectSub($this->teacherGroupCountQuery(), 'cantidad_grupos')
            ->with(['subject', 'career']);
    }

    /**
     * Subconsulta correlacionada: cuántos grupos dicta el docente en el par de cada fila.
     * Resuelve "mis materias" sin lanzar una consulta por registro.
     */
    private function teacherGroupCountQuery(): Builder
    {
        return Group::query()
            ->selectRaw('count(*)')
            ->whereColumn('grupo.id_carrera', 'materia_carrera.id_carrera')
            ->whereColumn('grupo.id_materia', 'materia_carrera.id_materia')
            ->where('grupo.id_usuario_docente', $this->teacherId())
            ->where('grupo.id_periodo', $this->activePeriodId())
            ->where('grupo.estado', RecordStatus::ACTIVE);
    }

    private function findPairOrFail(int $careerId, int $subjectId): SubjectCareer
    {
        $pair = $this->basePairQuery()
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

    private function markOwnGroups(Collection $groups): Collection
    {
        $teacherId = $this->teacherId();

        return $groups->each(function (Group $group) use ($teacherId): void {
            $group->es_mio = (string) $group->id_usuario_docente === $teacherId;
        });
    }
}
