<?php

namespace App\Exceptions\Academic;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class StudentRosterFileException extends RuntimeException
{
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
        ], 422);
    }
}