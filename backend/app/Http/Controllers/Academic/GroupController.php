<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\ShowGroupRequest;
use App\Http\Requests\Academic\StoreGroupRequest;
use App\Http\Requests\Academic\UpdateGroupRequest;
use App\Http\Resources\Academic\GroupResource;
use App\Http\Resources\Academic\SubjectCareerResource;
use App\Services\Academic\GroupService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Expone un grupo académico al docente que lo dicta, junto con el par al que pertenece.
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
        $group = $this->groupService->findGroup((int) $request->validated()['id_grupo']);

        $this->authorize('view', $group);

        $result = $this->groupService->showGroup($group);

        return $this->groupResponse($result);
    }

    /**
     * HU-18: registra un grupo dentro de un par materia-carrera.
     */
    public function store(StoreGroupRequest $request): JsonResponse
    {
        $result = $this->groupService->storeGroup($request->validated());

        return ApiResponse::created(
            $this->groupPayload($result),
            'Grupo registrado correctamente.'
        );
    }

    /**
     * actualiza los datos habilitados de un grupo existente.
     */
    public function update(UpdateGroupRequest $request, int $id_grupo): JsonResponse
    {
        $result = $this->groupService->updateGroup($id_grupo, $request->validated());

        return ApiResponse::success(
            $this->groupPayload($result),
            'Grupo actualizado correctamente.'
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