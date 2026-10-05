<?php

namespace App\Http\Controllers\Exams;

use App\Http\Controllers\Controller;
use App\Http\Requests\Exams\AssignAssistantClassroomRequest;
use App\Http\Resources\Exams\AssistantExamResource;
use App\Http\Resources\Exams\ClassroomResource;
use App\Http\Resources\Exams\ExamAssistantResource;
use App\Models\Exam;
use App\Services\Exams\AssistantClassroomService;
use App\Support\ApiResponse;
use App\Support\CurrentUser;
use Illuminate\Http\JsonResponse;

/**
 * Ambientes de los auxiliares de un examen (HU-09): el docente los asigna y el
 * auxiliar solo consulta el suyo.
 */
class AssistantClassroomController extends Controller
{
    private AssistantClassroomService $service;

    private CurrentUser $currentUser;

    public function __construct(AssistantClassroomService $service, CurrentUser $currentUser)
    {
        $this->service = $service;
        $this->currentUser = $currentUser;
    }

    /** GET /api/examenes/{exam}/auxiliares */
    public function index(Exam $exam): JsonResponse
    {
        $result = $this->service->listForExam($exam, $this->currentUser->teacherId());

        return ApiResponse::success([
            'estado'     => $result['estado'],
            'editable'   => $result['editable'],
            'ambientes'  => ClassroomResource::collection($result['classrooms']),
            'auxiliares' => ExamAssistantResource::collection($result['assistants']),
        ]);
    }

    /** PUT /api/examenes/{exam}/auxiliares/{user}/ambiente */
    public function update(AssignAssistantClassroomRequest $request, Exam $exam, string $user): JsonResponse
    {
        $assistant = $this->service->assign(
            $exam,
            $user,
            (int) $request->validated()['id_ambiente'],
            $this->currentUser->teacherId()
        );

        return ApiResponse::success(new ExamAssistantResource($assistant), 'Ambiente asignado correctamente.');
    }

    /** GET /api/auxiliar/examenes */
    public function myExams(): JsonResponse
    {
        return ApiResponse::success(
            AssistantExamResource::collection($this->service->listForAssistant((string) $this->currentUser->id()))
        );
    }
}
