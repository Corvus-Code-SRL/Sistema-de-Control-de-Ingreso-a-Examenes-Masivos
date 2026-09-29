<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Http\Resources\Academic\GroupOptionResource;
use App\Services\Academic\AssistantService;
use Illuminate\Http\JsonResponse;

/**
 * Grupos del docente en el período activo (HU-08).
 *
 * Lo consume el feature "Mis auxiliares" para poblar los selectores de
 * "Asignar auxiliar" y "Mover de grupo", que necesitan la lista completa
 * de grupos del docente, no solo donde el auxiliar ya está.
 */
class MyGroupsController extends Controller
{
    private AssistantService $assistantService;

    public function __construct(AssistantService $assistantService)
    {
        $this->assistantService = $assistantService;
    }

    public function index(): JsonResponse
    {
        $groups = $this->assistantService->listarMisGrupos();

        return response()->json([
            'data' => GroupOptionResource::collection($groups),
        ]);
    }
}