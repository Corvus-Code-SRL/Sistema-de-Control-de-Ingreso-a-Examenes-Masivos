<?php

namespace App\Http\Controllers\Exams;

use App\Http\Controllers\Controller;
use App\Services\Exams\ExamGetAllService;
use App\Http\Resources\Exams\ExamResource;
use Illuminate\Http\JsonResponse;

class ExamSchedulerController extends Controller
{
    public function getAll(ExamGetAllService $service): JsonResponse
    {
        try {
            $exams = $service->getAllExams();

            return response()->json([
                'message' => 'Exámenes obtenidos exitosamente',
                'data'    => ExamResource::collection($exams)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
