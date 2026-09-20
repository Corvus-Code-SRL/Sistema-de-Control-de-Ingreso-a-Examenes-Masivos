<?php

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use App\Http\Requests\Security\AssignRoleRequest;
use App\Http\Resources\Security\RoleResource;
use App\Models\User;
use App\Services\Security\UserRoleService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class UserRoleController extends Controller
{
    private UserRoleService $service;

    public function __construct(UserRoleService $service)
    {
        $this->service = $service;
    }

    /** GET /api/usuarios/{user}/rol — CA 16 */
    public function show(User $user): JsonResponse
    {
        $rol = $this->service->consultarRolActual($user);

        return ApiResponse::success($rol ? new RoleResource($rol) : null);
    }

    /** POST /api/usuarios/{user}/rol — CA 1, 2 */
    public function store(AssignRoleRequest $request, User $user): JsonResponse
    {
        $rol = $this->service->asignar(
            $user,
            (int) $request->validated()['id_rol'],
            auth()->id()            // null en Sprint 1
        );

        return ApiResponse::success(
            new RoleResource($rol),
            'Rol asignado correctamente.'
        );
    }

    /** GET /api/usuarios/{user}/asignaciones — CA 15 */
    public function asignaciones(User $user): JsonResponse
    {
        return ApiResponse::success($this->service->contarAsignacionesActivas($user));
    }
}