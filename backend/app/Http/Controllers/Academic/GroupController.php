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
use App\Support\CurrentUser;
use Illuminate\Http\JsonResponse;

/**
 * Expone, registra y actualiza grupos académicos dentro de su par materia-carrera.
 */
class GroupController extends Controller
{
    private GroupService $groupService;

    private CurrentUser $currentUser;

    public function __construct(GroupService $groupService, CurrentUser $currentUser)
    {
        $this->groupService = $groupService;
        $this->currentUser = $currentUser;
    }

    public function show(ShowGroupRequest $request): JsonResponse
    {
        $group = $this->groupService->findGroup((int) $request->validated()['id_grupo']);

        $teacherId = $this->currentUser->teacherId();

        $this->authorize('view', [$group, $teacherId]);

        $result = $this->groupService->showGroup($group, $teacherId);

        return $this->groupResponse($result);
    }

    /**
     * HU-18: registra un grupo dentro de un par materia-carrera.
     */
    public function store(StoreGroupRequest $request): JsonResponse
    {
        $result = $this->groupService->storeGroup($request->validated(), $this->currentUser->teacherId());

        return ApiResponse::created(
            $this->groupPayload($result),
            'Grupo registrado correctamente.'
        );
    }

    /**
     * HU-19: actualiza los datos habilitados de un grupo propio (número y período).
     */
    public function update(UpdateGroupRequest $request, int $id_grupo): JsonResponse
    {
        $group = $this->groupService->findGroup($id_grupo);

        $teacherId = $this->currentUser->teacherId();

        $this->authorize('update', [$group, $teacherId]);

        $result = $this->groupService->updateGroup($id_grupo, $request->validated(), $teacherId);

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
