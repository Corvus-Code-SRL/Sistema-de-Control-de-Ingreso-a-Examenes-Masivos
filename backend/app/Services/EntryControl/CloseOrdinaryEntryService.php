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

    /** El control de ingreso se abrió alguna vez: la ventana de invitaciones y cambios de nómina ya terminó. */
    public static function hasOpened(int $examId): bool
    {
        return Exam::query()->whereKey($examId)->whereNotNull('ingreso_abierto_en')->exists();
    }

    /** Control abierto: con apertura, sin cierre ordinario y mientras el examen sea PROGRAMADO o EN_CURSO. */
    public static function isOpen(Exam $exam): bool
    {
        return $exam->ingreso_abierto_en !== null
            && $exam->ingreso_ordinario_cerrado_en === null
            && in_array($exam->estado, [Exam::PROGRAMADO, Exam::EN_CURSO], true);
    }

    /**
     * Restringe una consulta de examen (Eloquent o Query Builder) a los de control abierto.
     * $table es el prefijo de la tabla o alias, con su punto final cuando se necesita.
     */
    public static function constrainToOpen($query, string $table = '')
    {
        return $query
            ->whereNotNull($table . 'ingreso_abierto_en')
            ->whereNull($table . 'ingreso_ordinario_cerrado_en')
            ->whereIn($table . 'estado', [Exam::PROGRAMADO, Exam::EN_CURSO]);
    }

    /** El ingreso con retraso (HU-037) solo existe tras el cierre ordinario, con el examen en curso. */
    public static function allowsLate(Exam $exam): bool
    {
        return $exam->estado === Exam::EN_CURSO
            && self::isClosed((int) $exam->id_examen);
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

            if (! self::isOpen($locked) || ! $this->snapshots->ready((int) $locked->id_examen)) {
                throw new ExamStateException('El ingreso ordinario no está abierto.');
            }

            if ($locked->estado !== Exam::EN_CURSO) {
                throw new ExamStateException(
                    'El ingreso ordinario solo se puede cerrar cuando el examen está en curso.'
                );
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
