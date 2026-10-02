<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\AddAssistantToGroupRequest;
use App\Http\Requests\Academic\AddAssistantToGroupsRequest;
use App\Http\Requests\Academic\SearchAssistantRequest;
use App\Http\Requests\Academic\EnableAssistantForExamRequest;
use App\Http\Resources\Academic\AssistantResource;
use App\Services\Academic\AssistantService;
use Illuminate\Http\JsonResponse;

/**
 * Gestión de auxiliares del docente (HU-08).
 *
 * Todos los endpoints verifican que el grupo o examen sea del docente actual
 * dentro de AssistantService. No hay auth real todavía.
 */
class AssistantController extends Controller
{
    private AssistantService $assistantService;

    public function __construct(AssistantService $assistantService)
    {
        $this->assistantService = $assistantService;
    }

    public function index(): JsonResponse
    {
        $auxiliares = $this->assistantService->listarMisAuxiliares();

        return response()->json([
            'data' => $auxiliares,
        ]);
    }

    public function buscar(SearchAssistantRequest $request): JsonResponse   // ← CAMBIO
    {
        $auxiliares = $this->assistantService->buscar(
            $request->validated()['criterio']
        );

        return response()->json([
            'data' => AssistantResource::collection($auxiliares),
        ]);
    }

    public function anadirAGrupo(AddAssistantToGroupRequest $request, int $id_grupo): JsonResponse
    {
        $this->assistantService->anadirAGrupo(
            $id_grupo,
            $request->validated()['id_usuario']
        );

        return response()->json([
            'message' => 'Auxiliar incorporado al grupo correctamente.',
        ], 201);
    }

    public function anadirAVariosGrupos(
        AddAssistantToGroupsRequest $request,
        string $id_usuario
    ): JsonResponse {
        $this->assistantService->anadirAVariosGrupos(
            $id_usuario,
            $request->validated()['grupos']
        );

        return response()->json([
            'message' => 'Auxiliar incorporado a los grupos seleccionados.',
        ], 201);
    }

    public function habilitarParaExamen(
        EnableAssistantForExamRequest $request,
        int $id_examen
    ): JsonResponse {
        $this->assistantService->habilitarParaExamen(
            $id_examen,
            $request->validated()['id_usuario']
        );

        return response()->json([
            'message' => 'Auxiliar habilitado para el examen correctamente.',
        ], 201);
    }

    public function quitarDeGrupo(int $id_grupo, string $id_usuario): JsonResponse
    {
        $this->assistantService->quitarDeGrupo($id_grupo, $id_usuario);

        return response()->json([
            'message' => 'Auxiliar quitado del grupo correctamente.',
        ]);
    }

    public function quitarDeExamen(int $id_examen, string $id_usuario): JsonResponse
    {
        $this->assistantService->quitarDeExamen($id_examen, $id_usuario);

        return response()->json([
            'message' => 'Auxiliar quitado del examen correctamente.',
        ]);
    }
}