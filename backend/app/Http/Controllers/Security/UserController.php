<?php

namespace App\Http\Controllers\Security;

use App\Exceptions\Security\SisNoDisponibleException;
use App\Exceptions\Security\SisNoValidoException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Security\StoreUserRequest;
use App\Http\Resources\Security\UserResource;
use App\Models\User;
use App\Services\Security\Contracts\SisGateway;
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

    /** GET /api/sis/verificar/{cod_sis} */
    public function verificarSis(string $codSis, SisGateway $sis): JsonResponse
    {
        // 1. Validar SIS caído
        if (! $sis->estaDisponible()) {
            throw new SisNoDisponibleException();
        }

        // 2. Validar cuenta duplicada (Busca en tu base de datos)
        $existente = User::where('cod_sis', $codSis)->first();
        if ($existente) {
            return response()->json([
                'message' => 'Los datos proporcionados no son válidos.',
                'errors'  => ['cod_sis' => ["Ya existe una cuenta con este código SIS: {$existente->nombre_completo}."]],
            ], 422);
        }

        // 3. Validar si no existe en la UMSS
        if (! $sis->existePersona($codSis)) {
            throw new SisNoValidoException();
        }

        // 4. Éxito
        return response()->json([
            'data' => [
                'nombre'   => 'Laura',
                'paterno'  => 'Mendoza',
                'materno'  => 'Rivas',
                'tipo'     => 'Docente',
                'facultad' => 'Facultad de Ciencias y Tecnología'
            ]
        ]);
    }
}
