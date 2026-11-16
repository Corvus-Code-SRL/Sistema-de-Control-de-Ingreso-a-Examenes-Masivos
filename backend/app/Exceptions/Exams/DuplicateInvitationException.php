<?php

namespace App\Exceptions\Exams;

use App\Support\ApiResponse;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * El docente ya tiene una invitación pendiente o aceptada para el examen (HU-026).
 */
class DuplicateInvitationException extends Exception
{
    protected $message = 'El docente ya tiene una invitación pendiente o aceptada para este examen.';

    public function render(Request $request): JsonResponse
    {
        return ApiResponse::error($this->getMessage(), 409);
    }
}
