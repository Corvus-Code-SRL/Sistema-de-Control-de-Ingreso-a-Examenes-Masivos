<?php

namespace App\Services\Exams\Distribution;

use App\Services\Security\AuditLogService;
use App\Support\SisCode;
use Illuminate\Support\Facades\DB;

/**
 * Una confirmación por guardado con todos los cambios, incluidas asignaciones que dejan de ser vigentes.
 *
 * @phpstan-import-type SavedAssignment from DistributionRosterQuery
 */
class DistributionAudit
{
    public function __construct(private AuditLogService $audit)
    {
    }

    /**
     * @param list<SavedAssignment> $before
     * @param list<SavedAssignment> $after
     */
    public function record(int $examId, array $before, array $after, string $authorId): void
    {
        $previous = array_column($before, 'id_ambiente', 'id_estudiante');
        $current = array_column($after, 'id_ambiente', 'id_estudiante');
        $studentIds = array_values(array_unique(array_merge(array_keys($previous), array_keys($current))));
        sort($studentIds, SORT_NUMERIC);
        $codes = DB::table('estudiante')->whereIn('id_estudiante', $studentIds)->pluck('cod_sis', 'id_estudiante');
        $changes = [];
        $oldAssignments = [];

        foreach ($studentIds as $studentId) {
            $old = $previous[$studentId] ?? null;
            $new = $current[$studentId] ?? null;

            if ($old !== $new) {
                $identity = ['id_estudiante' => $studentId, 'cod_sis' => SisCode::normalize($codes[$studentId])];
                $oldAssignments[] = $identity + ['id_ambiente' => $old];
                $changes[] = $identity + ['ambiente_anterior' => $old, 'ambiente_nuevo' => $new];
            }
        }

        $this->audit->registrar(
            'GUARDAR_DISTRIBUCION',
            'distribucion_ambiente',
            [
                'id_examen' => $examId,
                'version' => DistributionVersion::fromRows($before),
                'total_cambios' => count($changes),
                'cambios' => $oldAssignments,
            ],
            [
                'id_examen' => $examId,
                'version' => DistributionVersion::fromRows($after),
                'total_cambios' => count($changes),
                'cambios' => $changes,
            ],
            $authorId
        );
    }
}
