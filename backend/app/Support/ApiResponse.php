<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    public static function success($data, string $message = null, int $status = 200): JsonResponse
    {
        $payload = ['data' => $data];

        if ($message !== null) {
            $payload['mensaje'] = $message;
        }

        return response()->json($payload, $status);
    }

    public static function created($data, string $message = null): JsonResponse
    {
        return self::success($data, $message, 201);
    }

    /**
     * Los errores conservan la clave `message` de Laravel: es la que leen el
     * manejador de excepciones y el cliente HTTP del frontend.
     */
    public static function error(string $message, int $status = 400, array $extra = []): JsonResponse
    {
        return response()->json(array_merge(['message' => $message], $extra), $status);
    }
}
