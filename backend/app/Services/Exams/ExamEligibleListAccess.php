<?php

namespace App\Services\Exams;

use App\Exceptions\Exams\ExamOwnershipException;
use App\Exceptions\Exams\ExamStateException;
use App\Models\Exam;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Decide si la lista de habilitados de un examen se puede leer o escribir
 * ahora (HU-028).
 *
 * Escritura (PUT /habilitados, preview y confirm de importación): el
 * examen debe ser del docente y estar PROGRAMADO. En cualquier otro
 * estado la lista es de solo lectura.
 *
 * Lectura (GET /habilitados): el examen debe ser del docente. Cualquier
 * estado se permite.
 *
 * La propiedad se valida también aquí (no solo en el Controller) porque
 * el Service no debe confiar en quien lo llame.
 */
class ExamEligibleListAccess
{
    public function getAvailableForWrite(int $examId, string $teacherId): Exam
    {
        $exam = $this->findOwned($examId, $teacherId);

        if ($exam->estado !== Exam::PROGRAMADO) {
            throw new ExamStateException($this->notWritableMessage($exam->estado));
        }

        return $exam;
    }

    public function getAvailableForRead(int $examId, string $teacherId): Exam
    {
        return $this->findOwned($examId, $teacherId);
    }

    private function findOwned(int $examId, string $teacherId): Exam
    {
        $exam = Exam::query()->find($examId);

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
            Exam::EN_CURSO => 'El control de ingreso ya empezó. La lista de habilitados es de solo lectura.',
            Exam::FINALIZADO => 'El examen ya finalizó. La lista de habilitados es de solo lectura.',
            Exam::CANCELADO  => 'El examen está cancelado. La lista de habilitados es de solo lectura.',
            default          => 'La lista de habilitados solo puede modificarse mientras el examen está PROGRAMADO.',
        };
    }
}
