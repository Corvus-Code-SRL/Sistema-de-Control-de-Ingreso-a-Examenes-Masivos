<?php

namespace App\Services\Incidents;

use App\Models\Exam;
use App\Models\Role;
use App\Models\User;
use App\Services\Security\UserRoleService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/** Autoriza por examen y ambiente sin aplicar los horarios del control de ingreso. */
class ExamSituationAccessService
{
    private UserRoleService $roles;

    public function __construct(UserRoleService $roles)
    {
        $this->roles = $roles;
    }

    /** Devuelve null para el docente responsable, o el ambiente permitido al auxiliar. */
    public function roomScope(Exam $exam, User $actor): ?int
    {
        if ($exam->id_carrera === null || $actor->estado !== User::ESTADO_ACTIVO) {
            throw new AuthorizationException('No tiene permiso para consultar este examen.');
        }

        if (
            (string) $exam->id_usuario_docente === (string) $actor->id_usuario
            && $this->roles->isTeacher($actor->id_usuario)
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

        $isTeacher = $this->roles->isTeacher($actor->id_usuario);

        return Exam::query()->whereNotNull('examen.id_carrera')
            ->where(function ($query) use ($actor, $isTeacher) {
                $query->whereRaw('1 = 0');

                if ($isTeacher) {
                    $query->orWhere('examen.id_usuario_docente', $actor->id_usuario);
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
        return array_merge($this->roles->rolesFor('teaching'), [Role::AUXILIAR]);
    }
}
