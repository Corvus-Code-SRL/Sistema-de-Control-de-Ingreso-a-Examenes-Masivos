<?php

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use App\Http\Requests\Security\IndexUserRequest;
use App\Http\Requests\Security\ShowUserRequest;
use App\Http\Resources\Security\UserDetailResource;
use App\Http\Resources\Security\UserResource;
use App\Models\User;
use App\Services\Security\UserQueryService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Consulta de cuentas para el Administrador.
 *
 * Ambas respuestas incluyen el usuario actual en meta, para que la vista sepa
 * cuál es su propia cuenta sin depender de una sesión.
 */
class UserQueryController extends Controller
{
    private UserQueryService $users;

    public function __construct(UserQueryService $users)
    {
        $this->users = $users;
    }

    /** GET /api/usuarios */
    public function index(IndexUserRequest $request): AnonymousResourceCollection
    {
        return UserResource::collection($this->users->listUsers())
            ->additional(['meta' => $this->users->meta()]);
    }

    /** GET /api/usuarios/{user} */
    public function show(ShowUserRequest $request, User $user): UserDetailResource
    {
        return (new UserDetailResource($this->users->findUser($user)))
            ->additional(['meta' => $this->users->meta()]);
    }
}
