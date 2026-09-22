<?php

namespace App\Http\Controllers\Exams;

use App\Http\Controllers\Controller;
use App\Http\Requests\Exams\AssignGroupsRequest;
use App\Http\Resources\Exams\ExamResource;
use App\Models\Exam;
use App\Services\Exams\ExamGroupService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Asigna grupos académicos a un examen programado.
 */
class ExamGroupController extends Controller
{
    private ExamGroupService $groupService;

    public function __construct(ExamGroupService $groupService)
    {
        $this->groupService = $groupService;
    }

    public function store(AssignGroupsRequest $request, Exam $exam): JsonResponse
    {
        $exam = $this->groupService->assignGroups($exam, $request->validated()['grupos']);

        return ApiResponse::success(
            new ExamResource($exam),
            'Grupos asignados al examen correctamente.'
        );
    }
}
