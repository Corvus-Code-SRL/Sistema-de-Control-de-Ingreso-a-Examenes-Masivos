<?php

namespace App\Services\Academic;

use App\Models\Group;
use App\Models\Period;
use App\Support\RecordStatus;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Consulta y gestiona los grupos académicos de un par materia-carrera.
 *
 * El par se valida siempre contra el catálogo: un grupo solo es consultable o
 * registrable si su materia está activa en la carrera a la que pertenece.
 */
class GroupService
{
    private SubjectCatalogService $subjectCatalog;

    public function __construct(SubjectCatalogService $subjectCatalog)
    {
        $this->subjectCatalog = $subjectCatalog;
    }

    public function listGroupsForPair(int $careerId, int $subjectId, string $teacherId): array
    {
        $pair = $this->subjectCatalog->findSelectablePair($careerId, $subjectId, $teacherId);

        $groups = $this->markOwnGroups(
            $this->baseGroupQuery()
                ->where('grupo.id_carrera', $careerId)
                ->where('grupo.id_materia', $subjectId)
                ->where('grupo.id_periodo', $this->subjectCatalog->activePeriodId())
                ->orderBy('grupo.num_grupo')
                ->get(),
            $teacherId
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

    public function findGroup(int $groupId): Group
    {
        $group = $this->baseGroupQuery()
            ->where('grupo.id_grupo', $groupId)
            ->first();

        if ($group === null) {
            throw new ModelNotFoundException('No existe el grupo indicado.');
        }

        return $group;
    }

    /**
     * Arma el detalle de un grupo ya autorizado, con su par materia-carrera y periodo:
     * es la unidad sobre la que después se prepara la información de estudiantes.
     */
    public function showGroup(Group $group, string $teacherId): array
    {
        $pair = $this->subjectCatalog->findSelectablePair(
            (int) $group->id_carrera,
            (int) $group->id_materia,
            $teacherId
        );

        $this->markOwnGroup($group, $teacherId);

        $activePeriodId = $this->subjectCatalog->activePeriodId();

        return [
            'group' => $group,
            'pair' => $pair,
            'meta' => [
                'id_periodo_activo' => $activePeriodId,
                'es_periodo_activo' => (int) $group->id_periodo === $activePeriodId,
            ],
        ];
    }

    /**
     * Registra un nuevo grupo dentro de un par materia-carrera (HU-18).
     *
     * La única restricción de acceso (CA 7) es la de HU-16: el par debe existir y
     * estar activo (findSelectablePair). No hay vínculo docente-materia en el esquema;
     * registrar el primer grupo es justamente lo que incorpora la materia a
     * "Mis materias", así que no se exige ningún grupo previo.
     */
    public function storeGroup(array $data, string $teacherId): array
    {
        $pair = $this->subjectCatalog->findSelectablePair(
            (int) $data['id_carrera'],
            (int) $data['id_materia'],
            $teacherId
        );

        $periodId = (int) ($data['id_periodo'] ?? $this->subjectCatalog->activePeriodId());
        $period = $this->findPeriodOrFail($periodId);

        $this->assertNoDuplicateGroup(
            (int) $pair->id_carrera,
            (int) $pair->id_materia,
            $data['num_grupo'],
            $this->groupManagementFor($period),
            $periodId
        );

        $group = DB::transaction(function () use ($pair, $data, $period, $teacherId) {
            try {
                return Group::create([
                    'id_carrera' => $pair->id_carrera,
                    'id_materia' => $pair->id_materia,
                    'num_grupo' => $data['num_grupo'],
                    'gestion' => $this->groupManagementFor($period),
                    'estado' => RecordStatus::ACTIVE,
                    'id_usuario_docente' => $teacherId,
                    'id_periodo' => $period->id_periodo,
                ]);
            } catch (QueryException $exception) {
                // Red de seguridad ante una carrera entre dos solicitudes casi
                // simultáneas: la validación de arriba ya cubre el caso normal,
                // pero la constraint unq_grupo es la garantía real a nivel de datos.
                throw $this->isUniqueViolation($exception)
                    ? $this->duplicateGroupException()
                    : $exception;
            }
        });

        return $this->showGroup($this->findGroup((int) $group->id_grupo), $teacherId);
    }

    /**
     * id_carrera, id_materia e id_usuario_docente son inmutables: se leen del
     * propio grupo, nunca del payload recibido, aunque el FormRequest los reciba.
     */
    public function updateGroup(int $groupId, array $data, string $teacherId): array
    {
        $group = DB::transaction(function () use ($groupId, $data, $teacherId) {
            $group = Group::query()->lockForUpdate()->find($groupId);

            if ($group === null) {
                throw new ModelNotFoundException('No existe el grupo indicado.');
            }

            $this->assertGroupBelongsToTeacher($group, $teacherId);

            $periodId = (int) ($data['id_periodo'] ?? $group->id_periodo);
            $period = $this->findPeriodOrFail($periodId);

            $this->assertNoDuplicateGroup(
                (int) $group->id_carrera,
                (int) $group->id_materia,
                $data['num_grupo'],
                $this->groupManagementFor($period),
                $periodId,
                (int) $group->id_grupo
            );

            try {
                $group->fill([
                    'num_grupo' => $data['num_grupo'],
                    'gestion' => $this->groupManagementFor($period),
                    'id_periodo' => $period->id_periodo,
                ]);
                $group->save();
            } catch (QueryException $exception) {
                throw $this->isUniqueViolation($exception)
                    ? $this->duplicateGroupException()
                    : $exception;
            }

            return $group;
        });

        return $this->showGroup($this->findGroup((int) $group->id_grupo), $teacherId);
    }

    private function findPeriodOrFail(int $periodId): Period
    {
        $period = Period::query()->find($periodId);

        if ($period === null) {
            throw ValidationException::withMessages([
                'id_periodo' => ['El período académico seleccionado no existe.'],
            ]);
        }

        return $period;
    }

    /**
     * El formulario solo pide N° de grupo y período: no hay un input de "Gestión".
     * grupo.gestion (varchar) se deriva del periodo.gestion (smallint) elegido.
     */
    private function groupManagementFor(Period $period): string
    {
        return (string) $period->gestion;
    }

    /**
     * El nombre del docente se toma por join: el modelo de usuario todavía apunta a la
     * tabla por defecto de Laravel y su mapeo corresponde a la historia de autenticación.
     */
    private function baseGroupQuery(): Builder
    {
        return Group::query()
            ->withStudentCount()
            ->join('usuario', 'usuario.id_usuario', '=', 'grupo.id_usuario_docente')
            ->addSelect([
                'usuario.nombre as docente_nombre',
                'usuario.apellido_paterno as docente_apellido_paterno',
                'usuario.apellido_materno as docente_apellido_materno',
            ])
            ->with('period');
    }

    private function markOwnGroups(Collection $groups, string $teacherId): Collection
    {
        return $groups->each(function (Group $group) use ($teacherId): void {
            $this->markOwnGroup($group, $teacherId);
        });
    }

    private function markOwnGroup(Group $group, string $teacherId): void
    {
        $group->es_mio = (string) $group->id_usuario_docente === $teacherId;
    }

    /**
     * CA 8 (HU-18) y CA 5 (HU-19): la quíntupla completa (carrera, materia, número
     * de grupo, gestión, período) es lo que define duplicidad, nunca la materia sola.
     */
    private function assertNoDuplicateGroup(
        int $careerId,
        int $subjectId,
        string $groupNumber,
        string $management,
        int $periodId,
        ?int $excludeGroupId = null
    ): void {
        $exists = Group::query()
            ->where('id_carrera', $careerId)
            ->where('id_materia', $subjectId)
            ->where('num_grupo', $groupNumber)
            ->where('gestion', $management)
            ->where('id_periodo', $periodId)
            ->when($excludeGroupId !== null, function (Builder $query) use ($excludeGroupId): void {
                $query->where('id_grupo', '!=', $excludeGroupId);
            })
            ->exists();

        if ($exists) {
            throw $this->duplicateGroupException();
        }
    }

    private function duplicateGroupException(): ValidationException
    {
        return ValidationException::withMessages([
            'num_grupo' => [
                'Ya existe un grupo con esta identificación en la misma materia, '
                . 'carrera, gestión y período.',
            ],
        ]);
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        // 23505 es el SQLSTATE de "unique_violation" en PostgreSQL.
        return $exception->getCode() === '23505';
    }

    /**
     * Un docente no opera grupos ajenos.
     */
    private function assertGroupBelongsToTeacher(Group $group, string $teacherId): void
    {
        if ((string) $group->id_usuario_docente !== $teacherId) {
            throw new AuthorizationException('No tiene permiso sobre este grupo.');
        }
    }
}
