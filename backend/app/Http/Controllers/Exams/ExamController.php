<?php

namespace App\Http\Controllers\Exams;

use App\Http\Controllers\Controller;
use App\Http\Requests\Exams\CreateExamRequest;
use App\Http\Requests\Exams\UpdateExamRequest;
use App\Http\Resources\Exams\ClassroomResource;
use App\Http\Resources\Exams\ExamResource;
use App\Http\Resources\Exams\GroupResource;
use App\Http\Resources\Exams\SubjectResource;
use App\Models\Exam;
use App\Services\Exams\ExamService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Creación, modificación y cancelación de exámenes (HU-24).
 */
class ExamController extends Controller
{
    private ExamService $examService;

    public function __construct(ExamService $examService)
    {
        $this->examService = $examService;
    }

    /** GET /api/examenes/formulario */
    public function formOptions(): JsonResponse
    {
        $options = $this->examService->formOptions();

        return ApiResponse::success([
            'materias'  => SubjectResource::collection($options['pairs']),
            'ambientes' => ClassroomResource::collection($options['classrooms']),
            'grupos'    => GroupResource::collection($options['groups']),
        ]);
    }

    /** GET /api/examenes — vista Programados: los exámenes del docente actual. */
    public function index(): JsonResponse
    {
        $exams = $this->examService->listForCurrentTeacher();

        return ApiResponse::success(ExamResource::collection($exams));
    }

    /** GET /api/examenes/{exam} */
    public function show(Exam $exam): JsonResponse
    {
        $exam = $this->examService->find($exam);

        return ApiResponse::success(new ExamResource($exam));
    }

    /** POST /api/examenes */
    public function store(CreateExamRequest $request): JsonResponse
    {
        $exam = $this->examService->create($request->validated());

        return ApiResponse::created(new ExamResource($exam), 'Examen creado en estado Programado.');
    }

    /** PUT /api/examenes/{exam} */
    public function update(UpdateExamRequest $request, Exam $exam): JsonResponse
    {
        $exam = $this->examService->update($exam, $request->validated());

        return ApiResponse::success(new ExamResource($exam), 'Examen actualizado correctamente.');
    }

    /** POST /api/examenes/{exam}/cancelar */
    public function cancel(Exam $exam): JsonResponse
    {
        $exam = $this->examService->cancel($exam);

        return ApiResponse::success(new ExamResource($exam), 'Examen cancelado correctamente.');
    }
}
