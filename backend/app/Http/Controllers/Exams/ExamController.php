<?php

namespace App\Http\Controllers\Exams;

use App\Http\Controllers\Controller;
use App\Http\Requests\Exams\CreateExamRequest;
use App\Services\Exams\ExamCreationService;
use App\Http\Resources\Exams\ExamResource;
use Illuminate\Http\JsonResponse;

/**
 * Controlador API para la creación de exámenes.
 */
class ExamController extends Controller
{
    protected ExamCreationService $creationService;

    public function __construct(ExamCreationService $creationService)
    {
        $this->creationService = $creationService;
    }

    public function create(CreateExamRequest $request): JsonResponse
    {
        try {
            $exam = $this->creationService->createExam($request->validated());

            return response()->json([
                'message' => 'Examen creado exitosamente',
                'data'    => new ExamResource($exam->load(['classrooms', 'groups'])),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }
}