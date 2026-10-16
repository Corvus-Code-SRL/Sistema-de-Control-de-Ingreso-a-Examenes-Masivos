<?php

namespace App\Services\Academic;

use App\Models\AcademicOffering;
use App\Models\Group;
use App\Models\Period;
use App\Models\User;
use App\Services\Security\AuditLogService;
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

    private AuditLogService $auditLog;

    public function __construct(
    SubjectCatalogService $subjectCatalog,
    AuditLogService $auditLog
  ) {
      $this->subjectCatalog = $subjectCatalog;
      $this->auditLog = $auditLog;
  }

    public function listGroupsForPair(int $careerId, int $subjectId, string $teacherId): array
    {
        $pair = $this->subjectCatalog->findOfferedPair($careerId, $subjectId, $teacherId);

        $groups = $this->markOwnGroups(
            $this->baseGroupQuery()
                ->where('grupo.id_carrera', $careerId)
                ->where('grupo.id_materia', $subjectId)
                ->where('grupo.id_gestion_periodo', $this->subjectCatalog->activePeriodId())
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
                'docente' => ['nombre_completo' => $this->teacherName($teacherId)],
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
                'es_periodo_activo' => (int) $group->id_gestion_periodo === $activePeriodId,
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

        $this->assertPairIsOffered((int) $pair->id_carrera, (int) $pair->id_materia, $periodId);

        $this->assertNoDuplicateGroup(
            (int) $pair->id_carrera,
            (int) $pair->id_materia,
            $data['num_grupo'],
            $periodId
        );

       $group = DB::transaction(function () use ($pair, $data, $period, $teacherId) {
    try {
        $group = Group::create([
            'id_carrera' => $pair->id_carrera,
            'id_materia' => $pair->id_materia,
            'num_grupo' => $data['num_grupo'],
            'estado' => RecordStatus::ACTIVE,
            'id_usuario_docente' => $teacherId,
            'id_gestion_periodo' => $period->id_gestion_periodo,
        ]);
    } catch (QueryException $exception) {
        throw $this->translateDatabaseError($exception);
    }

    $this->auditLog->registrar(
        'CREAR',
        'grupo',
        null,
        $group->only([
            'id_grupo',
            'id_carrera',
            'id_materia',
            'num_grupo',
            'estado',
            'id_usuario_docente',
            'id_gestion_periodo',
        ]),
        $teacherId
    );

    return $group;
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

            $periodId = (int) ($data['id_periodo'] ?? $group->id_gestion_periodo);
            $period = $this->findPeriodOrFail($periodId);

            $this->assertPairIsOffered((int) $group->id_carrera, (int) $group->id_materia, $periodId);

            $this->assertNoDuplicateGroup(
                (int) $group->id_carrera,
                (int) $group->id_materia,
                $data['num_grupo'],
                $periodId,
                (int) $group->id_grupo
            );

            try {
                $group->fill([
                    'num_grupo' => $data['num_grupo'],
                    'id_gestion_periodo' => $period->id_gestion_periodo,
                ]);
                $group->save();
            } catch (QueryException $exception) {
                throw $this->translateDatabaseError($exception);
            }

            return $group;
        });

        return $this->showGroup($this->findGroup((int) $group->id_grupo), $teacherId);
    }

    /**
     * Nombre del docente que actúa: es el "Docente" que el formulario de registro muestra
     * como dato de solo lectura, y el dueño que tendrá el grupo al crearlo.
     */
    private function teacherName(string $teacherId): ?string
    {
        if ($teacherId === '') {
            return null;
        }

        $teacher = User::query()->find($teacherId);

        return $teacher === null ? null : $teacher->nombre_completo;
    }

    private function findPeriodOrFail(int $periodId): Period
    {
        $period = Period::query()->find($periodId);

        if ($period === null || $period->estado !== RecordStatus::ACTIVE) {
            throw ValidationException::withMessages([
                'id_periodo' => ['El período académico seleccionado no existe.'],
            ]);
        }

        return $period;
    }

    /**
     * Un grupo solo existe para un par ofertado en su período (oferta_academica activa). La clave
     * compuesta de grupo es la garantía a nivel de datos; esta validación da el mensaje claro.
     */
    private function assertPairIsOffered(int $careerId, int $subjectId, int $periodId): void
    {
        $isOffered = AcademicOffering::query()
            ->where('id_carrera', $careerId)
            ->where('id_materia', $subjectId)
            ->where('id_gestion_periodo', $periodId)
            ->where('estado', RecordStatus::ACTIVE)
            ->exists();

        if ($isOffered) {
            return;
        }

        throw $this->pairNotOfferedException();
    }

    private function pairNotOfferedException(): ValidationException
    {
        return ValidationException::withMessages([
            'id_periodo' => [
                'La materia no se ofrece en el período académico seleccionado. '
                . 'Elija otro período o solicite que se incluya en la oferta.',
            ],
        ]);
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
     * CA 8 (HU-18) y CA 5 (HU-19): (carrera, materia, número de grupo, período) define la
     * duplicidad, nunca la materia sola. La gestión ya viaja dentro del período.
     */
    private function assertNoDuplicateGroup(
        int $careerId,
        int $subjectId,
        string $groupNumber,
        int $periodId,
        ?int $excludeGroupId = null
    ): void {
        $exists = Group::query()
            ->where('id_carrera', $careerId)
            ->where('id_materia', $subjectId)
            ->where('num_grupo', $groupNumber)
            ->where('id_gestion_periodo', $periodId)
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

    /**
     * 23505 (unique_violation): otra solicitud creó el mismo grupo. 23503 (foreign_key_violation):
     * la oferta del par en ese período desapareció entre la validación y la escritura.
     */
    private function translateDatabaseError(QueryException $exception): \Throwable
    {
        switch ($exception->getCode()) {
            case '23505':
                return $this->duplicateGroupException();
            case '23503':
                return $this->pairNotOfferedException();
            default:
                return $exception;
        }
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
