<?php

namespace App\Exceptions\Exams;

use App\Support\ApiResponse;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * El usuario indicado no está habilitado como auxiliar del examen (HU-09).
 */
class ExamAssistantNotFoundException extends Exception
{
    protected $message = 'El auxiliar no está habilitado para este examen.';

    public function render(Request $request): JsonResponse
    {
        return ApiResponse::error($this->getMessage(), 404);
    }
}