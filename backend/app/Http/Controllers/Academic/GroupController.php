<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\ShowGroupRequest;
use App\Http\Resources\Academic\GroupResource;
use App\Http\Resources\Academic\SubjectCareerResource;
use App\Services\Academic\GroupService;
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

        return response()->json([
            'data' => [
                'grupo' => new GroupResource($result['group']),
                'materia' => new SubjectCareerResource($result['pair']),
            ],
            'meta' => $result['meta'],
        ]);
    }
}
