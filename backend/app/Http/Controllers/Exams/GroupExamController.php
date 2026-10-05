<?php

namespace App\Http\Controllers\Exams;

use App\Http\Controllers\Controller;
use App\Http\Requests\Exams\ShowGroupExamsRequest;
use App\Http\Resources\Exams\ExamResource;
use App\Services\Exams\ExamGroupService;
use App\Support\ApiResponse;
use App\Support\CurrentUser;
use Illuminate\Http\JsonResponse;

/**
 * Exámenes en los que participa un grupo (pestaña Exámenes del curso).
 */
class GroupExamController extends Controller
{
    private ExamGroupService $groupService;

    private CurrentUser $currentUser;

    public function __construct(ExamGroupService $groupService, CurrentUser $currentUser)
    {
        $this->groupService = $groupService;
        $this->currentUser = $currentUser;
    }

    /** GET /api/grupos/{id_grupo}/examenes */
    public function index(ShowGroupExamsRequest $request): JsonResponse
    {
        $group = $this->groupService->findGroup((int) $request->validated()['id_grupo']);

        $this->authorize('view', [$group, $this->currentUser->teacherId()]);

        return ApiResponse::success(ExamResource::collection($this->groupService->examsOfGroup($group)));
    }
}
