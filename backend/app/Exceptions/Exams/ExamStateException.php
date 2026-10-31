<?php

namespace App\Exceptions\Exams;

use App\Support\ApiResponse;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * La operación no es válida en el estado actual del examen: editar o cancelar
 * un examen cuyo control de ingreso ya empezó, o uno ya cancelado.
 */
class ExamStateException extends Exception
{
    public function render(Request $request): JsonResponse
    {
        return ApiResponse::error($this->getMessage(), 409);
    }
}
