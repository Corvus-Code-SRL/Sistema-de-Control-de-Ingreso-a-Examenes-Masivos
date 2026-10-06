<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\ListSubjectCareerAssignmentsRequest;
use App\Http\Requests\Academic\StoreSubjectCareerRequest;
use App\Http\Resources\Academic\CareerResource;
use App\Http\Resources\Academic\SubjectCareerAssignmentResource;
use App\Http\Resources\Academic\SubjectResource;
use App\Models\Career;
use App\Models\SubjectCareer;
use App\Services\Academic\SubjectCareerAssignmentService;
use App\Support\ApiResponse;
use App\Support\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SubjectCareerAssignmentController extends Controller
{
    private SubjectCareerAssignmentService $service;
    private CurrentUser $currentUser;

    public function __construct(
        SubjectCareerAssignmentService $service,
        CurrentUser $currentUser
    ) {
        $this->service = $service;
        $this->currentUser = $currentUser;
    }

    public function careers(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', SubjectCareer::class);

        return CareerResource::collection(
            $this->service->listActiveCareers()
        );
    }

    public function assignableSubjects(Career $career): AnonymousResourceCollection
    {
        $this->authorize('viewAny', SubjectCareer::class);

        return SubjectResource::collection(
            $this->service->listAssignableSubjects($career)
        );
    }

    public function index(ListSubjectCareerAssignmentsRequest $request): AnonymousResourceCollection
    {
        $careerId = $request->validated()['id_carrera'] ?? null;

        return SubjectCareerAssignmentResource::collection(
            $this->service->listAssignments($careerId === null ? null : (int) $careerId)
        )->additional([
            'meta' => [
                'carreras' => CareerResource::collection($this->service->listAssignedCareers())->resolve(),
            ],
        ]);
    }

    public function store(
        StoreSubjectCareerRequest $request,
        Career $career
    ): JsonResponse {
        $assignment = $this->service->assign(
            $career,
            (int) $request->validated()['id_materia'],
            (string) $this->currentUser->id()
        );

        return ApiResponse::created(
            new SubjectCareerAssignmentResource($assignment),
            'Materia asignada a la carrera correctamente.'
        );
    }
}