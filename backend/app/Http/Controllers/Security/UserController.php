<?php

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use App\Http\Requests\Security\StoreUserRequest;
use App\Http\Resources\Security\UserResource;
use App\Services\Security\UserService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    private UserService $service;

    public function __construct(UserService $service)
    {
        $this->service = $service;
    }

    /** POST /api/usuarios */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $usuario = $this->service->registrar($request->validated());

        // CA 9 — confirmación de registro exitoso
        return ApiResponse::created(
            new UserResource($usuario),
            'Cuenta creada correctamente.'
        );
    }
}