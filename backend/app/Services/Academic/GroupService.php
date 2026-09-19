<?php

namespace App\Services\Academic;

use App\Models\Group;
use App\Support\RecordStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

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
            $this->baseGroupQuery()
                ->where('grupo.id_carrera', $careerId)
                ->where('grupo.id_materia', $subjectId)
                ->where('grupo.id_periodo', $this->subjectCatalog->activePeriodId())
                ->orderBy('grupo.num_grupo')
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

    /**
     * El nombre del docente se toma por join: el modelo de usuario todavía apunta a la
     * tabla por defecto de Laravel y su mapeo corresponde a la historia de autenticación.
     */
    private function baseGroupQuery(): Builder
    {
        return Group::query()
            ->join('usuario', 'usuario.id_usuario', '=', 'grupo.id_usuario_docente')
            ->select('grupo.*')
            ->addSelect([
                'usuario.nombre as docente_nombre',
                'usuario.apellido_paterno as docente_apellido_paterno',
                'usuario.apellido_materno as docente_apellido_materno',
            ])
            ->selectSub($this->activeStudentCountQuery(), 'cantidad_estudiantes')
            ->with('period');
    }

    /**
     * Solo cuentan las inscripciones ACTIVAS: un retiro deja la fila en INACTIVO.
     */
    private function activeStudentCountQuery(): QueryBuilder
    {
        return DB::table('grupo_estudiante')
            ->selectRaw('count(*)')
            ->whereColumn('grupo_estudiante.id_grupo', 'grupo.id_grupo')
            ->where('grupo_estudiante.estado', RecordStatus::ACTIVE);
    }

    private function markOwnGroups(Collection $groups): Collection
    {
        $teacherId = $this->subjectCatalog->teacherId();

        return $groups->each(function (Group $group) use ($teacherId): void {
            $group->es_mio = (string) $group->id_usuario_docente === $teacherId;
        });
    }
}
