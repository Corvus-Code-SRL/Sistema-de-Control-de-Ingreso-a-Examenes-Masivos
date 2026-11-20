<?php

namespace App\Services\Exams;

use App\Exceptions\Exams\ExamOwnershipException;
use App\Exceptions\Exams\ExamStateException;
use App\Models\Exam;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Decide si la lista de habilitados de un examen se puede leer o escribir
 * ahora (HU-028).
 *
 * Ventana de escritura (PUT /habilitados, preview y confirm de la carga): el
 * examen es del docente, está PROGRAMADO y su control de ingreso todavía no se
 * abrió (ingreso_abierto_en nulo). Fuera de esa ventana la lista es de solo
 * lectura. Toda escritura pasa por isWritable() / assertWritable() /
 * lockForWrite() o por applyWritableWindow() para que la regla viva en un
 * solo lugar.
 *
 * Lectura (GET /habilitados): el examen debe ser del docente. Cualquier
 * estado se permite.
 *
 * La propiedad se valida también aquí (no solo en el Controller) porque
 * el Service no debe confiar en quien lo llame.
 */
class ExamEligibleListAccess
{
    public const WINDOW_CLOSED_MESSAGE =
        'La lista de habilitados ya no se puede modificar: el control de ingreso del examen ya se abrió.';

    public function isWritable(Exam $exam): bool
    {
        return $exam->estado === Exam::PROGRAMADO && $exam->ingreso_abierto_en === null;
    }

    /**
     * Restringe una consulta de exámenes a los que aún están en la ventana de escritura.
     *
     * @param Builder<Exam> $query
     * @return Builder<Exam>
     */
    public function applyWritableWindow(Builder $query): Builder
    {
        return $query
            ->where('estado', Exam::PROGRAMADO)
            ->whereNull('ingreso_abierto_en');
    }

    /** @throws ExamStateException */
    public function assertWritable(Exam $exam): void
    {
        if (! $this->isWritable($exam)) {
            throw new ExamStateException($this->notWritableMessage($exam->estado));
        }
    }

    /** Chequeo previo, sin bloqueo: sirve para fallar rápido antes de leer el archivo. */
    public function getAvailableForWrite(int $examId, string $teacherId): Exam
    {
        $exam = $this->findOwned($examId, $teacherId);

        $this->assertWritable($exam);

        return $exam;
    }

    /**
     * Bloquea la fila del examen y re-chequea la ventana bajo el bloqueo.
     * Debe llamarse dentro de una transacción.
     */
    public function lockForWrite(int $examId, string $teacherId): Exam
    {
        $exam = $this->findOwned($examId, $teacherId, true);

        $this->assertWritable($exam);

        return $exam;
    }

    public function getAvailableForRead(int $examId, string $teacherId): Exam
    {
        return $this->findOwned($examId, $teacherId);
    }

    private function findOwned(int $examId, string $teacherId, bool $lock = false): Exam
    {
        $query = Exam::query();

        if ($lock) {
            $query->lockForUpdate();
        }

        $exam = $query->find($examId);

        if ($exam === null) {
            throw new ModelNotFoundException('No existe el examen indicado.');
        }

        if ((string) $exam->id_usuario_docente !== $teacherId) {
            throw new ExamOwnershipException();
        }

        return $exam;
    }

    private function notWritableMessage(string $status): string
    {
        return match ($status) {
            Exam::FINALIZADO => 'El examen ya finalizó. La lista de habilitados es de solo lectura.',
            Exam::CANCELADO => 'El examen está cancelado. La lista de habilitados es de solo lectura.',
            default => self::WINDOW_CLOSED_MESSAGE,
        };
    }
}
