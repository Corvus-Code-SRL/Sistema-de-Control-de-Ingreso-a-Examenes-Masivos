<?php

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use App\Http\Requests\Security\StoreUserRequest;
use App\Http\Requests\Security\VerifySisRequest;
use App\Http\Resources\Security\UserResource;
use App\Services\Security\UserService;
use App\Support\ApiResponse;
use App\Support\CurrentUser;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    private UserService $service;

    private CurrentUser $currentUser;

    public function __construct(UserService $service, CurrentUser $currentUser)
    {
        $this->service = $service;
        $this->currentUser = $currentUser;
    }

    /** POST /api/usuarios */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $usuario = $this->service->registrar($request->validated(), $this->currentUser->id());

        // CA 9 — confirmación de registro exitoso
        return ApiResponse::created(
            new UserResource($usuario),
            'Cuenta creada correctamente.'
        );
    }

    /** GET /api/sis/verificar/{cod_sis} */
    public function verificarSis(VerifySisRequest $request, string $codSis): JsonResponse
    {
        return ApiResponse::success($this->service->verifySisCode($codSis));
    }
}
