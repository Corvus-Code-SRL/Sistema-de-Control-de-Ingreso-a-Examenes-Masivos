<?php

namespace App\Http\Controllers\Exams;

use App\Http\Controllers\Controller;
use App\Http\Resources\Exams\ExamAttendanceExamResource;
use App\Services\Exams\ExamAttendanceQueryService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Predis\CommunicationException;
use Predis\Response\ServerException;
use RedisException;

class ExamAttendanceController extends Controller
{
    public function index(Request $request, ExamAttendanceQueryService $service): JsonResponse
    {
        try {
            $exams = $service->listAvailable($request->user());
        } catch (CommunicationException | ServerException | RedisException $exception) {
            report($exception);

            return ApiResponse::error('No se pudo comprobar la apertura del control de ingreso.', 503);
        }

        return ApiResponse::success(ExamAttendanceExamResource::collection($exams));
    }
}
