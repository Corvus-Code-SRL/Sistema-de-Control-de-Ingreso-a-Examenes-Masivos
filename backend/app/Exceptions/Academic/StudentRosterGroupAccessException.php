<?php

namespace App\Exceptions\Academic;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentRosterGroupAccessException extends Exception
{
    private int $statusCode;

    public function __construct(
        string $message,
        int $statusCode
    ) {
        parent::__construct($message);

        $this->statusCode = $statusCode;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
        ], $this->statusCode);
    }
}