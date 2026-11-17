<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\ListExceptionalExamsRequest;
use App\Http\Requests\Academic\SearchExceptionalStudentRequest;
use App\Http\Requests\Academic\StoreExceptionalStudentRequest;
use App\Http\Resources\Academic\ExceptionalExamResource;
use App\Http\Resources\Academic\ExceptionalStudentCandidateResource;
use App\Http\Resources\Academic\ExceptionalStudentResource;
use App\Services\Academic\ExceptionalStudentService;
use App\Support\ApiResponse;
use App\Support\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Estudiantes excepcionales de un examen propio (HU-023).
 */
class ExceptionalStudentController extends Controller
{
    private ExceptionalStudentService $service;

    private CurrentUser $currentUser;

    public function __construct(ExceptionalStudentService $service, CurrentUser $currentUser)
    {
        $this->service = $service;
        $this->currentUser = $currentUser;
    }

    /** GET /api/docente/estudiantes-excepcionales/examenes */
    public function exams(ListExceptionalExamsRequest $request): AnonymousResourceCollection
    {
        return ExceptionalExamResource::collection(
            collect($this->service->teacherExams($this->currentUser->teacherId()))
        );
    }

    /** GET /api/docente/examenes/{id_examen}/estudiantes-excepcionales/buscar?cod_sis= */
    public function search(SearchExceptionalStudentRequest $request): JsonResponse
    {
        $candidate = $this->service->search(
            $request->examId(),
            $this->currentUser->teacherId(),
            $request->sisCode()
        );

        if ($candidate === null) {
            return ApiResponse::error('No se encontró a ningún estudiante con este código', 404);
        }

        return (new ExceptionalStudentCandidateResource($candidate))->response();
    }

    /** POST /api/docente/examenes/{id_examen}/estudiantes-excepcionales */
    public function store(StoreExceptionalStudentRequest $request): JsonResponse
    {
        $registered = $this->service->register(
            $request->examId(),
            $this->currentUser->teacherId(),
            $request->studentId(),
            $request->reason()
        );

        return (new ExceptionalStudentResource($registered))
            ->additional(['mensaje' => 'Estudiante excepcional registrado.'])
            ->response()
            ->setStatusCode(201);
    }
}
