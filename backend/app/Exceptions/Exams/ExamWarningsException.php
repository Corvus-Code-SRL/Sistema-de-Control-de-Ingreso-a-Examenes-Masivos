<?php

namespace App\Exceptions\Exams;

use App\Support\ApiResponse;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * El examen tiene advertencias que el docente no confirmó: nombre duplicado en la
 * misma fecha, o superposición de horario con otro examen suyo o de un ambiente.
 *
 * No es un rechazo definitivo: al reenviar con confirmar_advertencias el examen se
 * guarda. Las advertencias viajan en `errors` para que el cliente las lea igual que
 * los errores de validación.
 */
class ExamWarningsException extends Exception
{
    /** @var array<string, string[]> */
    private array $warnings;

    public function __construct(array $warnings)
    {
        parent::__construct('El examen tiene advertencias. Revíselas y confirme para continuar.');

        $this->warnings = $warnings;
    }

    public function warnings(): array
    {
        return $this->warnings;
    }

    public function render(Request $request): JsonResponse
    {
        return ApiResponse::error($this->getMessage(), 409, ['errors' => $this->warnings]);
    }
}
