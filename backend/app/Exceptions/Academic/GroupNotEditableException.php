<?php

namespace App\Exceptions\Academic;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * HU-019: el grupo existe y es del docente, pero hoy no se puede editar.
 *
 * Responde 422 con el motivo en `message`, igual que el bloqueo de la nómina.
 */
class GroupNotEditableException extends Exception
{
    public const EXAM_IN_PROGRESS = 'No se puede editar mientras un examen del grupo está en curso.';

    /** Solo el período vigente admite escritura (HU-006). */
    public const PAST_PERIOD = 'Este grupo pertenece a un período anterior; solo se puede consultar.';

    /** Usar examInProgress() o pastPeriod(); es público porque ErrorReportingTest instancia cada excepción. */
    public function __construct(string $message)
    {
        parent::__construct($message);
    }

    /** CA-5: un examen del grupo está en ingreso o en curso. */
    public static function examInProgress(): self
    {
        return new self(self::EXAM_IN_PROGRESS);
    }

    /** CA-6: el grupo pertenece a un período anterior. */
    public static function pastPeriod(): self
    {
        return new self(self::PAST_PERIOD);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 422);
    }
}
