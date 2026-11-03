<?php

namespace App\Http\Controllers\Exams;

use App\Http\Controllers\Controller;
use App\Http\Requests\Exams\IndexExamHistoryRequest;
use App\Http\Requests\Exams\ShowExamHistoryRequest;
use App\Http\Resources\Exams\ExamHistoryResource;
use App\Models\Exam;
use App\Services\Exams\ExamHistoryService;
use App\Support\ApiResponse;
use App\Support\CurrentUser;
use Illuminate\Http\JsonResponse;

class ExamHistoryController extends Controller
{
    public function __construct(
        private ExamHistoryService $history,
        private CurrentUser $currentUser
    ) {
    }

    public function index(IndexExamHistoryRequest $request): JsonResponse
    {
        return ApiResponse::success(
            new ExamHistoryResource($this->history->index($this->currentUser->teacherId(), $request->validated()))
        );
    }

    public function show(ShowExamHistoryRequest $request, Exam $exam): JsonResponse
    {
        return ApiResponse::success(new ExamHistoryResource($this->history->show($exam, $this->currentUser->teacherId())));
    }
}
