<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\ShowGroupRequest;
use App\Http\Requests\Academic\StoreGrupoRequest;
use App\Http\Requests\Academic\UpdateGrupoRequest;
use App\Http\Resources\Academic\GroupResource;
use App\Http\Resources\Academic\SubjectCareerResource;
use App\Services\Academic\GroupService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Expone un grupo académico de forma independiente, junto con el par al que pertenece.
 */
class GroupController extends Controller
{
    private GroupService $groupService;

    public function __construct(GroupService $groupService)
    {
        $this->groupService = $groupService;
    }

    public function show(ShowGroupRequest $request): JsonResponse
    {
        $result = $this->groupService->showGroup((int) $request->validated()['id_grupo']);

        return $this->groupResponse($result);
    }

    /**
     * HU-18: registra un grupo dentro de un par materia-carrera.
     */
    public function store(StoreGrupoRequest $request): JsonResponse
    {
        $result = $this->groupService->storeGroup($request->validated());

        return ApiResponse::created(
            $this->groupPayload($result),
            'Grupo registrado correctamente.'
        );
    }

    private function groupResponse(array $result): JsonResponse
    {
        return response()->json([
            'data' => [
                'grupo' => new GroupResource($result['group']),
                'materia' => new SubjectCareerResource($result['pair']),
            ],
            'meta' => $result['meta'],
        ]);
    }

    private function groupPayload(array $result): array
    {
        return [
            'grupo' => new GroupResource($result['group']),
            'materia' => new SubjectCareerResource($result['pair']),
        ];
    }
}