<?php

namespace App\Http\Controllers\Exams;

use App\Http\Controllers\Controller;
use App\Http\Requests\Exams\CancelExamRequest;
use App\Http\Requests\Exams\CreateExamRequest;
use App\Http\Requests\Exams\FinishExamRequest;
use App\Http\Requests\Exams\UpdateExamRequest;
use App\Http\Resources\Exams\ClassroomResource;
use App\Http\Resources\Exams\ExamResource;
use App\Http\Resources\Exams\GroupResource;
use App\Http\Resources\Exams\SubjectResource;
use App\Models\Exam;
use App\Services\Exams\ExamService;
use App\Services\Exams\ExamLifecycleService;
use App\Support\ApiResponse;
use App\Support\CurrentUser;
use Illuminate\Http\JsonResponse;

/**
 * Creación, modificación y cancelación de exámenes (HU-24).
 */
class ExamController extends Controller
{
    private ExamService $examService;

    private CurrentUser $currentUser;

    public function __construct(ExamService $examService, CurrentUser $currentUser)
    {
        $this->examService = $examService;
        $this->currentUser = $currentUser;
    }

    /** GET /api/examenes/formulario */
    public function formOptions(): JsonResponse
    {
        $options = $this->examService->formOptions($this->currentUser->teacherId());

        return ApiResponse::success([
            'materias'  => SubjectResource::collection($options['pairs']),
            'ambientes' => ClassroomResource::collection($options['classrooms']),
            'grupos'    => GroupResource::collection($options['groups']),
        ]);
    }

    /** GET /api/examenes — vista Programados: los exámenes del docente actual. */
    public function index(): JsonResponse
    {
        $exams = $this->examService->listForTeacher($this->currentUser->teacherId());

        return ApiResponse::success(ExamResource::collection($exams));
    }

    /** GET /api/examenes/{exam} */
    public function show(Exam $exam): JsonResponse
    {
        $exam = $this->examService->find($exam, $this->currentUser->teacherId());

        return ApiResponse::success(new ExamResource($exam));
    }

    /** POST /api/examenes */
    public function store(CreateExamRequest $request): JsonResponse
    {
        $exam = $this->examService->create($request->validated(), $this->currentUser->teacherId());

        return ApiResponse::created(new ExamResource($exam), 'Examen creado en estado Programado.');
    }

    /** PUT /api/examenes/{exam} */
    public function update(UpdateExamRequest $request, Exam $exam): JsonResponse
    {
        $exam = $this->examService->update($exam, $request->validated(), $this->currentUser->teacherId());

        return ApiResponse::success(new ExamResource($exam), 'Examen actualizado correctamente.');
    }

    /** POST /api/examenes/{exam}/cancelar */
    public function cancel(CancelExamRequest $request, Exam $exam): JsonResponse
    {
        $exam = $this->examService->cancel($exam, $this->currentUser->teacherId());

        return ApiResponse::success(new ExamResource($exam), 'Examen cancelado correctamente.');
    }

    /** POST /api/examenes/{exam}/finalizar */
    public function finish(FinishExamRequest $request, Exam $exam, ExamLifecycleService $lifecycle): JsonResponse
    {
        $exam = $lifecycle->finishManually($exam, $this->currentUser->teacherId());

        return ApiResponse::success(new ExamResource($exam), 'Examen finalizado correctamente.');
    }
}
