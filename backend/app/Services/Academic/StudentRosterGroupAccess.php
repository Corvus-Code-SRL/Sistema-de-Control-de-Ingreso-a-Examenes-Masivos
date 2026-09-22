<?php

namespace App\Services\Academic;

use App\Exceptions\Academic\StudentRosterGroupAccessException;
use App\Models\Group;
use App\Support\RecordStatus;

class StudentRosterGroupAccess
{
    public function getAvailable(int $groupId): Group
    {
        $group = Group::query()->findOrFail($groupId);

        $teacherId = (string) config(
            'sciem.docente_fijo_id'
        );

        $activePeriodId = (int) config(
            'sciem.periodo_activo_id'
        );

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

        if (
            (int) $group->id_periodo
            !== $activePeriodId
        ) {
            throw new StudentRosterGroupAccessException(
                'El grupo no pertenece al período académico activo.',
                422
            );
        }

        return $group;
    }
}