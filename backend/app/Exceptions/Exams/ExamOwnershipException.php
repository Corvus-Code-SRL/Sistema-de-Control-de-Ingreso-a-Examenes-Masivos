<?php

namespace App\Exceptions\Exams;

use App\Support\ApiResponse;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Solo el docente que creó el examen puede modificarlo o cancelarlo.
 */
class ExamOwnershipException extends Exception
{
    protected $message = 'Solo el docente que creó el examen puede modificarlo o cancelarlo.';

    public function render(Request $request): JsonResponse
    {
        return ApiResponse::error($this->getMessage(), 403);
    }
}
