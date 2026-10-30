<?php

namespace App\Http\Controllers\Exams;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Services\Exams\ExamInProgressQueryService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExamInProgressController extends Controller
{
    public function show(
        Request $request,
        Exam $exam,
        ExamInProgressQueryService $service
    ): JsonResponse {
        return ApiResponse::success(
            $service->show($exam, $request->user())
        );
    }
}
