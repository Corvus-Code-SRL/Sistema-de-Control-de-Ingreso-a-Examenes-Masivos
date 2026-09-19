<?php

namespace App\Services\Academic;

use App\Models\Group;
use Illuminate\Database\Eloquent\Collection;

/**
 * Consulta los grupos académicos de un par materia-carrera.
 *
 * El par se valida siempre contra el catálogo: un grupo solo es consultable si su
 * materia está activa en la carrera a la que pertenece.
 */
class GroupService
{
    private SubjectCatalogService $subjectCatalog;

    public function __construct(SubjectCatalogService $subjectCatalog)
    {
        $this->subjectCatalog = $subjectCatalog;
    }

    public function listGroupsForPair(int $careerId, int $subjectId): array
    {
        $pair = $this->subjectCatalog->findSelectablePair($careerId, $subjectId);

        $groups = $this->markOwnGroups(
            Group::query()
                ->where('id_carrera', $careerId)
                ->where('id_materia', $subjectId)
                ->where('id_periodo', $this->subjectCatalog->activePeriodId())
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
                'id_periodo_activo' => $this->subjectCatalog->activePeriodId(),
            ],
        ];
    }

    private function markOwnGroups(Collection $groups): Collection
    {
        $teacherId = $this->subjectCatalog->teacherId();

        return $groups->each(function (Group $group) use ($teacherId): void {
            $group->es_mio = (string) $group->id_usuario_docente === $teacherId;
        });
    }
}
