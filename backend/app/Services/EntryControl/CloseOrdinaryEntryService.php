<?php

namespace App\Services\EntryControl;

use App\Exceptions\Exams\ExamStateException;
use App\Models\Exam;
use App\Models\User;
use App\Services\Security\AuditLogService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class CloseOrdinaryEntryService
{
    public function __construct(
        private EntryControlSnapshotService $snapshots,
        private AuditLogService $auditLog
    ) {}

    public static function hasOpened(int $examId): bool
    {
        return Exam::query()->whereKey($examId)->whereNotNull('ingreso_abierto_en')->exists();
    }

    public static function allowsLate(Exam $exam): bool
    {
        if (! in_array($exam->estado, [Exam::PROGRAMADO, Exam::EN_CURSO], true)) {
            return false;
        }

        $examId = (int) $exam->id_examen;

        return self::isClosed($examId)
            || ($exam->estado === Exam::EN_CURSO && ! self::hasOpened($examId));
    }

    public static function isClosed(int $examId): bool
    {
        return Exam::query()->whereKey($examId)->whereNotNull('ingreso_ordinario_cerrado_en')->exists();
    }

    public function close(Exam $exam, User $actor): void
    {
        DB::transaction(function () use ($exam, $actor): void {
            $locked = Exam::query()->whereKey($exam->id_examen)->lockForUpdate()->firstOrFail();

            if ($actor->estado !== User::ESTADO_ACTIVO
                || (string) $locked->id_usuario_docente !== (string) $actor->id_usuario) {
                throw new AuthorizationException('Solo el docente del examen puede cerrar el ingreso ordinario.');
            }

            if (! in_array($locked->estado, [Exam::PROGRAMADO, Exam::EN_INGRESO, Exam::EN_CURSO], true)
                || $locked->ingreso_ordinario_cerrado_en !== null
                || ! $this->snapshots->ready((int) $locked->id_examen)) {
                throw new ExamStateException('El ingreso ordinario no está abierto.');
            }

            $locked->ingreso_ordinario_cerrado_en = now();
            $locked->save();

            $this->snapshots->deactivate((int) $locked->id_examen);

            $this->auditLog->registrar(
                'MODIFICAR',
                'examen',
                ['id_examen' => $locked->id_examen],
                ['id_examen' => $locked->id_examen, 'evento' => 'CIERRE_INGRESO_ORDINARIO'],
                (string) $actor->id_usuario
            );
        });
    }
}
