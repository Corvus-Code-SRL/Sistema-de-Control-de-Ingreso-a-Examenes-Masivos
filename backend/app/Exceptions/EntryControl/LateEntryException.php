<?php

namespace App\Exceptions\EntryControl;

use App\Services\EntryControl\VerificationService;
use App\Support\ApiResponse;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * El ingreso con retraso (HU-037) no puede registrarse o solicitarse. Responde con el
 * veredicto para que el cliente distinga el caso: 409 si el estado ya no lo permite y
 * 422 si el estudiante no cumple las reglas de verificación, igual que HU-032.
 */
class LateEntryException extends Exception
{
    public const EXAM_NOT_IN_PROGRESS = 'EXAMEN_NO_EN_CURSO';
    public const PENDING_REQUEST = 'SOLICITUD_PENDIENTE';
    public const REQUEST_RESOLVED = 'SOLICITUD_RESUELTA';
    public const OTHER_EXAM = 'EN_OTRO_EXAMEN';
    public const TIME_OVER = 'TIEMPO_CUMPLIDO';

    public const EXAM_NOT_IN_PROGRESS_MESSAGE =
        'Solo puede registrar ingresos con retraso mientras el examen está en curso.';
    public const PENDING_REQUEST_MESSAGE =
        'Existe una solicitud pendiente para este estudiante. Autorícela o rechácela.';
    public const REQUEST_RESOLVED_MESSAGE = 'La solicitud ya fue resuelta.';
    public const OTHER_EXAM_MESSAGE =
        'El estudiante ya ingresó a otro examen en curso. Confirme para continuar.';
    public const TIME_OVER_MESSAGE =
        'El tiempo del examen terminó; ya no se pueden registrar ingresos con retraso.';

    private const CONFLICT = 409;
    private const UNPROCESSABLE = 422;

    private string $verdict;
    private int $status;
    /** @var array<string, mixed> */
    private array $extra;

    /** @param array<string, mixed> $extra */
    public function __construct(string $verdict, string $message, int $status, array $extra = [])
    {
        parent::__construct($message);
        $this->verdict = $verdict;
        $this->status = $status;
        $this->extra = $extra;
    }

    public static function examNotInProgress(): self
    {
        return new self(self::EXAM_NOT_IN_PROGRESS, self::EXAM_NOT_IN_PROGRESS_MESSAGE, self::CONFLICT);
    }

    public static function pendingRequest(): self
    {
        return new self(self::PENDING_REQUEST, self::PENDING_REQUEST_MESSAGE, self::CONFLICT);
    }

    public static function requestResolved(): self
    {
        return new self(self::REQUEST_RESOLVED, self::REQUEST_RESOLVED_MESSAGE, self::CONFLICT);
    }

    /**
     * Conflicto sin confirmar: viaja la advertencia para que el cliente la muestre.
     *
     * @param array<string, mixed> $warning
     */
    public static function otherExam(array $warning): self
    {
        return new self(self::OTHER_EXAM, self::OTHER_EXAM_MESSAGE, self::CONFLICT, ['otro_examen' => $warning]);
    }

    public static function timeOver(): self
    {
        return new self(self::TIME_OVER, self::TIME_OVER_MESSAGE, self::CONFLICT);
    }

    /** Veredicto no autorizado de la verificación: el duplicado es un conflicto; lo demás, datos inválidos. */
    public static function denied(string $verdict, string $message): self
    {
        return new self(
            $verdict,
            $message,
            $verdict === VerificationService::DUPLICATE ? self::CONFLICT : self::UNPROCESSABLE
        );
    }

    public function verdict(): string
    {
        return $this->verdict;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function render(Request $request): JsonResponse
    {
        return ApiResponse::error($this->getMessage(), $this->status, ['veredicto' => $this->verdict] + $this->extra);
    }
}
