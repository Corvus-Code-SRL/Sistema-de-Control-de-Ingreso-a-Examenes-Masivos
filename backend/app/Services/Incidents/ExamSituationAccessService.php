<?php

namespace App\Services\Incidents;

use App\Exceptions\Exams\ExamStateException;
use App\Models\Exam;
use App\Models\Role;
use App\Models\User;
use App\Services\EntryControl\CloseOrdinaryEntryService;
use App\Services\Exams\ExamCollaboratorService;
use App\Services\Security\UserRoleService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/** Autoriza por examen y ambiente sin aplicar los horarios del control de ingreso. */
class ExamSituationAccessService
{
    /**
     * Estados en los que se puede registrar una situación: con el control de ingreso abierto (un examen
     * PROGRAMADO solo cuenta si ya abrió) o con el examen en curso, aunque ya se haya cerrado el ingreso ordinario.
     */
    public const REGISTRABLE_STATES = [Exam::PROGRAMADO, Exam::EN_CURSO];

    private UserRoleService $roles;

    private ExamCollaboratorService $collaborators;

    /** Función de sciem.access cuyos roles controlan el examen (HU-057: teaching; HU-042: incidents). */
    private string $feature = 'teaching';

    public function __construct(UserRoleService $roles, ExamCollaboratorService $collaborators)
    {
        $this->roles = $roles;
        $this->collaborators = $collaborators;
    }

    /** Copia que autoriza con los roles de otra función, sin depender de las capacidades de HU-057. */
    public function forFeature(string $feature): self
    {
        $copy = clone $this;
        $copy->feature = $feature;

        return $copy;
    }

    /** Devuelve null para un docente del examen (creador o colaborador), o el ambiente permitido al auxiliar. */
    public function roomScope(Exam $exam, User $actor): ?int
    {
        if ($exam->id_carrera === null || $actor->estado !== User::ESTADO_ACTIVO) {
            throw new AuthorizationException('No tiene permiso para consultar este examen.');
        }

        if (
            $this->collaborators->isTeacherOf($exam, (string) $actor->id_usuario)
            && $this->roles->canUse($actor->id_usuario, $this->feature)
        ) {
            return null;
        }

        if (! $this->roles->hasAnyActiveRole($actor->id_usuario, $this->controllerRoles())) {
            throw new AuthorizationException('No tiene permiso para consultar este examen.');
        }

        $assignment = DB::table('examen_auxiliar')
            ->where('id_examen', $exam->id_examen)
            ->where('id_usuario', $actor->id_usuario)
            ->first(['id_ambiente']);

        if ($assignment === null) {
            throw new AuthorizationException('No está habilitado como controlador de este examen.');
        }

        $roomIds = DB::table('examen_ambiente')
            ->where('id_examen', $exam->id_examen)
            ->pluck('id_ambiente')
            ->map(fn ($id) => (int) $id)
            ->all();

        $roomId = $assignment->id_ambiente === null ? null : (int) $assignment->id_ambiente;

        // Se conserva la regla existente: sin asignación explícita, solo sirve un examen de una sola aula.
        if ($roomId === null && count($roomIds) === 1) {
            $roomId = $roomIds[0];
        }

        if ($roomId === null || ! in_array($roomId, $roomIds, true)) {
            throw new AuthorizationException('Debe tener un ambiente asignado para controlar este examen.');
        }

        return $roomId;
    }

    /** Filtra el catálogo sin consultar asignaciones una por una. */
    public function visibleExams(User $actor): Builder
    {
        if (
            $actor->estado !== User::ESTADO_ACTIVO
            || ! $this->roles->hasAnyActiveRole($actor->id_usuario, $this->controllerRoles())
        ) {
            throw new AuthorizationException('No tiene permiso para consultar situaciones.');
        }

        $isTeacher = $this->roles->canUse($actor->id_usuario, $this->feature);

        return Exam::query()->whereNotNull('examen.id_carrera')
            ->where(function ($query) use ($actor, $isTeacher) {
                $query->whereRaw('1 = 0');

                if ($isTeacher) {
                    $query->orWhere(
                        fn ($taught) => $this->collaborators->whereTaughtBy($taught, (string) $actor->id_usuario)
                    );
                }

                $query->orWhereExists(function ($assignment) use ($actor) {
                    $assignment->selectRaw('1')->from('examen_auxiliar as ea')
                        ->whereColumn('ea.id_examen', 'examen.id_examen')
                        ->where('ea.id_usuario', $actor->id_usuario)
                        ->where(function ($room) {
                            $room->whereExists(function ($assigned) {
                                $assigned->selectRaw('1')->from('examen_ambiente as er')
                                    ->whereColumn('er.id_examen', 'ea.id_examen')
                                    ->whereColumn('er.id_ambiente', 'ea.id_ambiente');
                            })->orWhere(function ($single) {
                                $single->whereNull('ea.id_ambiente')->whereRaw(
                                    '(SELECT count(*) FROM examen_ambiente er WHERE er.id_examen = ea.id_examen) = 1'
                                );
                            });
                        });
                });
            });
    }

    public function isRegistrable(Exam $exam): bool
    {
        return $exam->estado === Exam::EN_CURSO || CloseOrdinaryEntryService::isOpen($exam);
    }

    /** Limita una consulta de exámenes a los que admiten situaciones (ver REGISTRABLE_STATES). */
    public function onlyRegistrable(Builder $query): Builder
    {
        return $query->where(function (Builder $registrable): void {
            $registrable->where('examen.estado', Exam::EN_CURSO)
                ->orWhere(fn (Builder $open) => CloseOrdinaryEntryService::constrainToOpen($open, 'examen.'));
        });
    }

    public function assertRegistrable(Exam $exam): void
    {
        if (! $this->isRegistrable($exam)) {
            throw new ExamStateException(
                'Solo se pueden registrar situaciones con el ingreso abierto o con el examen en curso.'
            );
        }
    }

    /** @param array<int, string> $states */
    public function assertInStates(Exam $exam, array $states, string $message): void
    {
        if (! in_array($exam->estado, $states, true)) {
            throw new ExamStateException($message);
        }
    }

    public function assertRoomAllowed(Exam $exam, int $roomId, ?int $roomScope): void
    {
        $belongsToExam = DB::table('examen_ambiente')
            ->where('id_examen', $exam->id_examen)
            ->where('id_ambiente', $roomId)
            ->exists();

        if (! $belongsToExam || ($roomScope !== null && $roomScope !== $roomId)) {
            throw new AuthorizationException('No tiene permiso para controlar este ambiente.');
        }
    }

    /**
     * Roles que controlan un examen: los que ejercen la docencia según sciem.access, incluido el
     * docente invitado, y el Auxiliar habilitado.
     *
     * @return array<int, string>
     */
    private function controllerRoles(): array
    {
        return array_merge($this->roles->rolesFor($this->feature), [Role::AUXILIAR]);
    }
}
