<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\ListAssignableSubjectsRequest;
use App\Http\Requests\Academic\ListSubjectCareerAssignmentsRequest;
use App\Http\Requests\Academic\StoreSubjectCareerRequest;
use App\Http\Resources\Academic\CareerResource;
use App\Http\Resources\Academic\PeriodResource;
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

    public function assignableSubjects(
        ListAssignableSubjectsRequest $request,
        Career $career
    ): AnonymousResourceCollection {
        $period = $this->service->activePeriod((int) $request->validated()['id_periodo']);

        return SubjectResource::collection(
            $this->service->listAssignableSubjects($career, $period)
        );
    }

    public function index(ListSubjectCareerAssignmentsRequest $request): AnonymousResourceCollection
    {
        $validated = $request->validated();
        $careerId = $validated['id_carrera'] ?? null;
        $period = $this->service->activePeriod((int) $validated['id_periodo']);

        return SubjectCareerAssignmentResource::collection(
            $this->service->listAssignments($period, $careerId === null ? null : (int) $careerId)
        )->additional([
            'meta' => [
                'periodo' => (new PeriodResource($period))->resolve(),
                'carreras' => CareerResource::collection($this->service->listCareersForPeriod($period))->resolve(),
            ],
        ]);
    }

    public function store(
        StoreSubjectCareerRequest $request,
        Career $career
    ): JsonResponse {
        $validated = $request->validated();

        $offering = $this->service->assign(
            $career,
            (int) $validated['id_materia'],
            (int) $validated['id_periodo'],
            (string) $this->currentUser->id()
        );

        return ApiResponse::created(
            new SubjectCareerAssignmentResource($offering),
            'Materia asignada a la carrera en el período correctamente.'
        );
    }
}
