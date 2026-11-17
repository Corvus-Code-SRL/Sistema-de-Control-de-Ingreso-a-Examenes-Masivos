<?php

namespace App\Services\Exams;

use App\Exceptions\Exams\CollaboratorInvitationOwnershipException;
use App\Exceptions\Exams\DuplicateInvitationException;
use App\Exceptions\Exams\ExamStateException;
use App\Models\Exam;
use App\Models\ExamInvitation;
use App\Models\User;
use App\Services\Academic\SubjectCatalogService;
use App\Services\Security\AuditLogService;
use App\Services\Security\UserRoleService;
use App\Support\Database\AccentInsensitiveSearch;
use App\Support\RecordStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Invitaciones a docentes colaboradores (HU-026).
 *
 * Los docentes que dictan la misma materia pueden dar un examen común. El docente que creó el examen invita a
 * otro docente mientras el examen está PROGRAMADO; la invitación nace PENDIENTE y aquí termina esta historia:
 * aceptarla o rechazarla es de HU-027, y dejarla SIN_EFECTO al salir el examen de PROGRAMADO es de
 * ExamInvitationExpiryService.
 */
class CollaboratorInvitationService
{
    private const SEARCH_LIMIT = 20;

    private ExamService $exams;

    private SubjectCatalogService $subjectCatalog;

    private UserRoleService $roles;

    private AuditLogService $auditLog;

    public function __construct(
        ExamService $exams,
        SubjectCatalogService $subjectCatalog,
        UserRoleService $roles,
        AuditLogService $auditLog
    ) {
        $this->exams = $exams;
        $this->subjectCatalog = $subjectCatalog;
        $this->roles = $roles;
        $this->auditLog = $auditLog;
    }

    /** Exámenes PROGRAMADOS del docente que aún no abren el control: los únicos a los que puede invitar. */
    public function listInvitableExams(string $teacherId): Collection
    {
        return $this->exams->listScheduledForTeacher($teacherId, [Exam::PROGRAMADO])
            ->filter(fn (Exam $exam) => $exam->ingreso_abierto_en === null)
            ->values();
    }

    /**
     * Docentes que se pueden invitar al examen y coinciden con la búsqueda por nombre o código SIS.
     *
     * Cada resultado trae `ya_invitado`: tiene una invitación pendiente o aceptada a este examen.
     *
     * @return Collection<int, User>
     */
    public function searchCandidates(Exam $exam, string $teacherId, string $criteria): Collection
    {
        $this->assertTeacherGiven($teacherId);
        $this->assertCanInvite($exam, $teacherId);

        $criteria = AccentInsensitiveSearch::normalize($criteria);

        if ($criteria === '') {
            return new Collection();
        }

        $pattern = AccentInsensitiveSearch::pattern($criteria);

        $candidates = $this->eligibleTeachers($exam, $teacherId)
            ->where(function (Builder $query) use ($pattern) {
                $query->whereRaw(AccentInsensitiveSearch::condition('usuario.cod_sis'), [$pattern])
                    ->orWhereRaw(AccentInsensitiveSearch::condition('usuario.nombre'), [$pattern])
                    ->orWhereRaw(AccentInsensitiveSearch::condition('usuario.apellido_paterno'), [$pattern])
                    ->orWhereRaw(AccentInsensitiveSearch::condition('usuario.apellido_materno'), [$pattern])
                    ->orWhereRaw(
                        AccentInsensitiveSearch::condition(
                            "CONCAT(usuario.nombre, ' ', usuario.apellido_paterno, ' ',"
                            . " COALESCE(usuario.apellido_materno, ''))"
                        ),
                        [$pattern]
                    );
            })
            ->orderBy('usuario.apellido_paterno')
            ->orderBy('usuario.nombre')
            ->limit(self::SEARCH_LIMIT)
            ->get();

        $invitedIds = ExamInvitation::query()
            ->current()
            ->where('id_examen', $exam->id_examen)
            ->whereIn('id_docente_invitado', $candidates->pluck('id_usuario'))
            ->pluck('id_docente_invitado')
            ->map(fn ($id) => (string) $id)
            ->all();

        foreach ($candidates as $candidate) {
            $candidate->setAttribute('ya_invitado', in_array((string) $candidate->id_usuario, $invitedIds, true));
        }

        return $candidates;
    }

    /**
     * Envía la invitación: queda PENDIENTE y se registra en la bitácora.
     *
     * Se bloquea la fila del examen: así dos invitaciones simultáneas al mismo docente se ordenan, y una
     * invitación no se cruza con la apertura del control de ingreso (que bloquea la misma fila). El índice
     * único parcial de la tabla es el respaldo si algo se saltara el bloqueo.
     */
    public function send(
        Exam $exam,
        string $teacherId,
        string $invitedId,
        bool $allowGroups,
        bool $listByGroup = false
    ): ExamInvitation {
        $this->assertTeacherGiven($teacherId);

        // La lista separada por grupo (HU-027) solo tiene sentido si el colaborador puede sumar sus grupos.
        if ($listByGroup && ! $allowGroups) {
            throw ValidationException::withMessages([
                'lista_por_grupo' => ['Solo puede separar la lista por grupo si permite agregar grupos.'],
            ]);
        }

        try {
            $invitation = DB::transaction(function () use ($exam, $teacherId, $invitedId, $allowGroups, $listByGroup) {
                $locked = Exam::query()->whereKey($exam->id_examen)->lockForUpdate()->firstOrFail();

                $this->assertCanInvite($locked, $teacherId);

                $invited = User::query()->findOrFail($invitedId);

                $this->assertInvitable($locked, $teacherId, $invited);

                $alreadyInvited = ExamInvitation::query()
                    ->current()
                    ->where('id_examen', $locked->id_examen)
                    ->where('id_docente_invitado', $invited->id_usuario)
                    ->exists();

                if ($alreadyInvited) {
                    throw new DuplicateInvitationException();
                }

                $invitation = ExamInvitation::create([
                    'id_examen' => $locked->id_examen,
                    'id_docente_creador' => $teacherId,
                    'id_docente_invitado' => $invited->id_usuario,
                    'fecha_invitacion' => Carbon::now(),
                    'estado' => ExamInvitation::PENDIENTE,
                    'permite_agregar_grupos' => $allowGroups,
                    'lista_por_grupo' => $listByGroup,
                ]);

                $this->auditLog->registrar(
                    'INVITAR_COLABORADOR',
                    'invitacion_examen',
                    null,
                    [
                        'id_invitacion' => $invitation->id_invitacion,
                        'id_examen' => $locked->id_examen,
                        'id_docente_invitado' => (string) $invited->id_usuario,
                        'estado' => ExamInvitation::PENDIENTE,
                        'permite_agregar_grupos' => $allowGroups,
                        'lista_por_grupo' => $listByGroup,
                    ],
                    $teacherId
                );

                return $invitation;
            });
        } catch (QueryException $exception) {
            // 23505: el índice único parcial atajó una invitación vigente que el chequeo no vio.
            if ($exception->getCode() === '23505') {
                throw new DuplicateInvitationException();
            }

            throw $exception;
        }

        return $invitation->load($this->sentRelations());
    }

    /** Invitaciones enviadas por el docente, de la más reciente a la más antigua. */
    public function listSent(string $teacherId): Collection
    {
        $this->assertTeacherGiven($teacherId);

        return ExamInvitation::query()
            ->with($this->sentRelations())
            ->where('id_docente_creador', $teacherId)
            ->orderByDesc('fecha_invitacion')
            ->orderByDesc('id_invitacion')
            ->get();
    }

    /** @return array<int, string> */
    private function sentRelations(): array
    {
        return ['exam.subject', 'exam.career', 'exam.classrooms', 'userInvited'];
    }

    /**
     * Docentes activos con un grupo activo de la materia del examen en el período vigente, sin el propio
     * docente. Se compara solo la materia (no la carrera), igual que al asignar grupos al examen. Ejercen la
     * docencia los roles de `teaching`: Docente e Invitado.
     */
    private function eligibleTeachers(Exam $exam, string $teacherId): Builder
    {
        $periodId = $this->subjectCatalog->activePeriodId();

        return User::query()
            ->select('usuario.*')
            ->activos()
            ->where('usuario.id_usuario', '<>', $teacherId)
            ->whereHas('activeRoles', function (Builder $roles) {
                $roles->whereIn('nombre_rol', $this->roles->rolesFor('teaching'));
            })
            ->whereHas('groups', function (Builder $groups) use ($exam, $periodId) {
                $groups->where('grupo.id_materia', $exam->id_materia)
                    ->where('grupo.id_gestion_periodo', $periodId)
                    ->where('grupo.estado', RecordStatus::ACTIVE);
            });
    }

    private function assertInvitable(Exam $exam, string $teacherId, User $invited): void
    {
        if ((string) $invited->id_usuario === $teacherId) {
            throw ValidationException::withMessages([
                'id_docente' => ['No puede invitarse a sí mismo.'],
            ]);
        }

        $isEligible = $this->eligibleTeachers($exam, $teacherId)
            ->whereKey($invited->id_usuario)
            ->exists();

        if (! $isEligible) {
            throw ValidationException::withMessages([
                'id_docente' => ['El docente no dicta grupos de la materia del examen en el período vigente.'],
            ]);
        }
    }

    private function assertCanInvite(Exam $exam, string $teacherId): void
    {
        if ((string) $exam->id_usuario_docente !== $teacherId) {
            throw new CollaboratorInvitationOwnershipException();
        }

        if ($exam->estado !== Exam::PROGRAMADO || $exam->ingreso_abierto_en !== null) {
            throw new ExamStateException(
                $exam->estado === Exam::CANCELADO
                    ? 'El examen está cancelado: ya no se puede invitar docentes.'
                    : 'El control de ingreso del examen ya se inició: ya no se puede invitar docentes.'
            );
        }
    }

    private function assertTeacherGiven(string $teacherId): void
    {
        if ($teacherId === '') {
            throw new RuntimeException('No hay un docente autenticado que ejecute la operación.');
        }
    }
}
