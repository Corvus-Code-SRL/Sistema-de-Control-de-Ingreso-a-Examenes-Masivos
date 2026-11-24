<?php

namespace App\Services\Exams\Distribution;

use App\Exceptions\Exams\ExamStateException;
use App\Services\Security\AuditLogService;
use App\Support\Exams\DistributionMessages;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Deja de usar la distribución de un examen (HU-031). Es irreversible. Al registrar manda PostgreSQL;
 * la marca de Redis la aplica también al verificar sin reconstruir el snapshot.
 *
 * @phpstan-import-type DistributionView from RoomDistributionQueryService
 */
class RoomDistributionStopService
{
    public const AUDIT_ACTION = 'DEJAR_USAR_DISTRIBUCION';

    public function __construct(
        private RoomDistributionAccess $access,
        private DistributionRosterQuery $roster,
        private RoomDistributionQueryService $query,
        private AuditLogService $audit,
        private DistributionSuspensionFlag $flag
    ) {
    }

    /** @return DistributionView */
    public function stop(int $examId, string $teacherId): array
    {
        $view = DB::transaction(function () use ($examId, $teacherId): array {
            $exam = $this->access->lockForStop($examId, $teacherId);

            if (! $this->access->canStop($exam, $teacherId, $this->roster->savedRows($examId) !== [])) {
                throw new ExamStateException(DistributionMessages::CANNOT_STOP);
            }

            $stoppedAt = Carbon::now('UTC')->format('Y-m-d H:i:sP');
            DB::table('examen')->where('id_examen', $examId)->update([
                'distribucion_suspendida_en' => $stoppedAt,
                'id_usuario_suspende_distribucion' => $teacherId,
            ]);
            $this->audit->registrar(
                self::AUDIT_ACTION,
                'examen',
                ['id_examen' => $examId, 'distribucion_suspendida_en' => null],
                ['id_examen' => $examId, 'distribucion_suspendida_en' => $stoppedAt],
                $teacherId
            );

            return $this->query->current($examId, $teacherId);
        });
        $this->flag->mark($examId);

        return $view;
    }
}
