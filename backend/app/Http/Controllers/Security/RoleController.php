<?php

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use App\Http\Requests\Security\IndexRoleRequest;
use App\Http\Resources\Security\RoleResource;
use App\Services\Security\RoleService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Expone el catálogo de roles asignables de SCIEM.
 */
class RoleController extends Controller
{
    private RoleService $roles;

    public function __construct(RoleService $roles)
    {
        $this->roles = $roles;
    }

    /** GET /api/roles */
    public function index(IndexRoleRequest $request): JsonResponse
    {
        return ApiResponse::success(RoleResource::collection($this->roles->listAssignableRoles()));
    }
}
