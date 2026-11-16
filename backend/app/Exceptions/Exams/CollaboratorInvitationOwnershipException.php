<?php

namespace App\Exceptions\Exams;

use App\Support\ApiResponse;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Solo el docente que creó el examen puede invitar colaboradores a él (HU-026).
 */
class CollaboratorInvitationOwnershipException extends Exception
{
    protected $message = 'Solo el docente que creó el examen puede invitar colaboradores a él.';

    public function render(Request $request): JsonResponse
    {
        return ApiResponse::error($this->getMessage(), 403);
    }
}
