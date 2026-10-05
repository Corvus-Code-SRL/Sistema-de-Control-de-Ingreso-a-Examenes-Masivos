<?php

namespace App\Http\Controllers\Exams;

use App\Http\Controllers\Controller;
use App\Http\Requests\Exams\AssignGroupsRequest;
use App\Http\Resources\Exams\ExamResource;
use App\Models\Exam;
use App\Services\Exams\ExamGroupService;
use App\Support\ApiResponse;
use App\Support\CurrentUser;
use Illuminate\Http\JsonResponse;

/**
 * Asigna grupos académicos a un examen programado.
 */
class ExamGroupController extends Controller
{
    private ExamGroupService $groupService;

    private CurrentUser $currentUser;

    public function __construct(ExamGroupService $groupService, CurrentUser $currentUser)
    {
        $this->groupService = $groupService;
        $this->currentUser = $currentUser;
    }

    public function store(AssignGroupsRequest $request, Exam $exam): JsonResponse
    {
        $exam = $this->groupService->assignGroups(
            $exam,
            $request->validated()['grupos'],
            $this->currentUser->teacherId()
        );

        return ApiResponse::success(
            new ExamResource($exam),
            'Grupos asignados al examen correctamente.'
        );
    }
}
