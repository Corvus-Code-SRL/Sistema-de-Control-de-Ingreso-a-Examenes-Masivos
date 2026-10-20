<?php

namespace App\Services\Academic;

use App\Exceptions\Academic\GroupNotEditableException;
use App\Models\AcademicOffering;
use App\Models\Group;
use App\Models\Period;
use App\Models\User;
use App\Services\Exams\ExamRosterLockService;
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
    /** Campos del grupo que la bitácora guarda al editarlo. */
    private const AUDITED_FIELDS = ['id_grupo', 'num_grupo', 'id_gestion_periodo'];

    private SubjectCatalogService $subjectCatalog;

    private ExamRosterLockService $rosterLock;

    private AuditLogService $auditLog;

    public function __construct(
        SubjectCatalogService $subjectCatalog,
        ExamRosterLockService $rosterLock,
        AuditLogService $auditLog
    ) {
        $this->subjectCatalog = $subjectCatalog;
        $this->rosterLock = $rosterLock;
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
                // La vista de edición avisa antes de que el docente intente guardar.
                'edicion_bloqueada' => $this->rosterLock->isLocked((int) $group->id_grupo),
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
     * HU-019: solo cambia el número de grupo. id_carrera, id_materia, id_usuario_docente y el
     * período son inmutables: se leen del propio grupo, nunca del payload recibido.
     */
    public function updateGroup(int $groupId, array $data, string $teacherId): array
    {
        $group = DB::transaction(function () use ($groupId, $data, $teacherId) {
            $group = Group::query()->lockForUpdate()->find($groupId);

            if ($group === null) {
                throw new ModelNotFoundException('No existe el grupo indicado.');
            }

            $this->assertGroupBelongsToTeacher($group, $teacherId);

            $periodId = (int) $group->id_gestion_periodo;

            // Solo el período vigente admite escritura (HU-006): un grupo anterior solo se consulta.
            if ($periodId !== $this->subjectCatalog->activePeriodId()) {
                throw GroupNotEditableException::pastPeriod();
            }

            if ($this->rosterLock->isLocked((int) $group->id_grupo)) {
                throw GroupNotEditableException::examInProgress();
            }

            $this->assertPeriodIsUnchanged($data, $periodId);

            $this->assertNoDuplicateGroup(
                (int) $group->id_carrera,
                (int) $group->id_materia,
                $data['num_grupo'],
                $periodId,
                (int) $group->id_grupo
            );

            $before = $group->only(self::AUDITED_FIELDS);

            $group->fill(['num_grupo' => $data['num_grupo']]);

            // Guardar sin cambios no es una modificación: no escribe ni deja rastro en la bitácora.
            if (! $group->isDirty()) {
                return $group;
            }

            try {
                $group->save();
            } catch (QueryException $exception) {
                throw $this->translateDatabaseError($exception, $data['num_grupo']);
            }

            $this->auditLog->registrar(
                'MODIFICAR',
                'grupo',
                $before,
                $group->only(self::AUDITED_FIELDS),
                $teacherId
            );

            return $group;
        });

        return $this->showGroup($this->findGroup((int) $group->id_grupo), $teacherId);
    }

    /**
     * El período no se edita (HU-019): enviarlo igual al actual es válido, cambiarlo no.
     */
    private function assertPeriodIsUnchanged(array $data, int $currentPeriodId): void
    {
        if (! isset($data['id_periodo']) || (int) $data['id_periodo'] === $currentPeriodId) {
            return;
        }

        throw ValidationException::withMessages([
            'id_periodo' => ['El período del grupo no se puede cambiar.'],
        ]);
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
     * CA 8 (HU-018) y CA 3 (HU-019): (carrera, materia, número de grupo, período) define la
     * duplicidad, nunca la materia sola. La gestión ya viaja dentro del período. Con
     * `$excludeGroupId` es una edición, que nombra el grupo con el que choca.
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

        if (! $exists) {
            return;
        }

        throw $excludeGroupId === null
            ? $this->duplicateGroupException()
            : $this->duplicateGroupOnUpdateException($groupNumber);
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

    private function duplicateGroupOnUpdateException(string $groupNumber): ValidationException
    {
        return ValidationException::withMessages([
            'num_grupo' => ["Ya existe el grupo {$groupNumber} para esta materia y período."],
        ]);
    }

    /**
     * 23505 (unique_violation): otra solicitud creó el mismo grupo. 23503 (foreign_key_violation):
     * la oferta del par en ese período desapareció entre la validación y la escritura.
     * `$updatedGroupNumber` indica que la escritura era una edición (HU-019).
     */
    private function translateDatabaseError(
        QueryException $exception,
        ?string $updatedGroupNumber = null
    ): \Throwable {
        switch ($exception->getCode()) {
            case '23505':
                return $updatedGroupNumber === null
                    ? $this->duplicateGroupException()
                    : $this->duplicateGroupOnUpdateException($updatedGroupNumber);
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
