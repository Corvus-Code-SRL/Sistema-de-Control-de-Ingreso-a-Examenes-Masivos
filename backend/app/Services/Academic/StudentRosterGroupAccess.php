<?php

namespace App\Services\Academic;

use App\Exceptions\Academic\StudentRosterGroupAccessException;
use App\Models\Group;
use App\Services\Exams\ExamRosterLockService;
use App\Support\RecordStatus;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Decide si la nómina de un grupo se puede cargar ahora: debe ser del docente, estar activo,
 * pertenecer al período activo y no estar congelada por un examen en ingreso o en curso.
 *
 * La propiedad ya la autoriza GroupPolicy::manageRoster en el Controller; aquí se repite
 * porque el Service no debe confiar en quien lo llame.
 */
class StudentRosterGroupAccess
{
    private ExamRosterLockService $rosterLock;

    private SubjectCatalogService $subjectCatalog;

    public function __construct(ExamRosterLockService $rosterLock, SubjectCatalogService $subjectCatalog)
    {
        $this->rosterLock = $rosterLock;
        $this->subjectCatalog = $subjectCatalog;
    }

    public function getAvailable(int $groupId, string $teacherId): Group
    {
        $group = Group::query()->find($groupId);

        if ($group === null) {
            throw new ModelNotFoundException('No existe el grupo indicado.');
        }

        if (
            (string) $group->id_usuario_docente
            !== $teacherId
        ) {
            throw new StudentRosterGroupAccessException(
                'El grupo no pertenece al docente actual.',
                403
            );
        }

        if ($group->estado !== RecordStatus::ACTIVE) {
            throw new StudentRosterGroupAccessException(
                'El grupo no está activo.',
                422
            );
        }

        // Sin período activo configurado lanza el error de configuración, no un 422 engañoso.
        if (
            (int) $group->id_periodo
            !== $this->subjectCatalog->activePeriodId()
        ) {
            throw new StudentRosterGroupAccessException(
                'El grupo no pertenece al período académico activo.',
                422
            );
        }

        if ($this->rosterLock->isLocked((int) $group->id_grupo)) {
            throw new StudentRosterGroupAccessException(
                'La nómina no puede modificarse mientras un examen del grupo '
                . 'está en ingreso o en curso.',
                422
            );
        }

        return $group;
    }
}
