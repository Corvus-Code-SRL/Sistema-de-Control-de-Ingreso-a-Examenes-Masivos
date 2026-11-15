<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\ListExceptionalGroupsRequest;
use App\Http\Requests\Academic\SearchExceptionalStudentRequest;
use App\Http\Requests\Academic\StoreExceptionalStudentRequest;
use App\Http\Resources\Academic\ExceptionalGroupResource;
use App\Http\Resources\Academic\ExceptionalStudentCandidateResource;
use App\Http\Resources\Academic\ExceptionalStudentResource;
use App\Services\Academic\ExceptionalStudentService;
use App\Support\ApiResponse;
use App\Support\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Estudiantes excepcionales de un grupo propio (HU-023).
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

    /** GET /api/docente/estudiantes-excepcionales/grupos */
    public function groups(ListExceptionalGroupsRequest $request): AnonymousResourceCollection
    {
        return ExceptionalGroupResource::collection(
            collect($this->service->teacherGroups($this->currentUser->teacherId()))
        );
    }

    /**
     * GET /api/docente/grupos/{id_grupo}/estudiantes-excepcionales/buscar?cod_sis=
     *
     * @return JsonResponse|ExceptionalStudentCandidateResource
     */
    public function search(SearchExceptionalStudentRequest $request)
    {
        $candidate = $this->service->search(
            $request->groupId(),
            $this->currentUser->teacherId(),
            $request->sisCode()
        );

        if ($candidate === null) {
            return ApiResponse::error('No se encontró a ningún estudiante con este código', 404);
        }

        return new ExceptionalStudentCandidateResource($candidate);
    }

    /** POST /api/docente/grupos/{id_grupo}/estudiantes-excepcionales */
    public function store(StoreExceptionalStudentRequest $request): JsonResponse
    {
        $registered = $this->service->register(
            $request->groupId(),
            $this->currentUser->teacherId(),
            $request->studentId(),
            $request->reason(),
            $request->careerId()
        );

        return (new ExceptionalStudentResource($registered))
            ->additional(['mensaje' => 'Estudiante excepcional registrado.'])
            ->response()
            ->setStatusCode(201);
    }
}
