<?php

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use App\Http\Requests\Security\AssignRoleRequest;
use App\Http\Requests\Security\ShowActiveAssignmentsRequest;
use App\Http\Requests\Security\ShowUserRoleRequest;
use App\Http\Resources\Security\RoleResource;
use App\Models\User;
use App\Services\Security\UserRoleService;
use App\Support\ApiResponse;
use App\Support\CurrentUser;
use Illuminate\Http\JsonResponse;

/**
 * Consulta y asignación del rol de una cuenta.
 */
class UserRoleController extends Controller
{
    private UserRoleService $userRoles;

    private CurrentUser $currentUser;

    public function __construct(UserRoleService $userRoles, CurrentUser $currentUser)
    {
        $this->userRoles = $userRoles;
        $this->currentUser = $currentUser;
    }

    /** GET /api/usuarios/{user}/rol */
    public function show(ShowUserRoleRequest $request, User $user): JsonResponse
    {
        $role = $this->userRoles->findActiveRole($user);

        return ApiResponse::success($role ? new RoleResource($role) : null);
    }

    /** POST /api/usuarios/{user}/rol */
    public function store(AssignRoleRequest $request, User $user): JsonResponse
    {
        $role = $this->userRoles->assignRole(
            $user,
            (int) $request->validated()['id_rol'],
            $this->currentUser->id()
        );

        return ApiResponse::success(new RoleResource($role), 'Rol asignado correctamente.');
    }

    /** GET /api/usuarios/{user}/asignaciones */
    public function assignments(ShowActiveAssignmentsRequest $request, User $user): JsonResponse
    {
        return ApiResponse::success($this->userRoles->countActiveAssignments($user));
    }
}
