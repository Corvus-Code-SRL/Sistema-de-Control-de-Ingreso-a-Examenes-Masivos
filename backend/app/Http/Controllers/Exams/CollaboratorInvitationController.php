<?php

namespace App\Http\Controllers\Exams;

use App\Http\Controllers\Controller;
use App\Http\Requests\Exams\ListCollaboratorInvitationsRequest;
use App\Http\Requests\Exams\SearchCollaboratorsRequest;
use App\Http\Requests\Exams\SendCollaboratorInvitationRequest;
use App\Http\Resources\Exams\CollaboratorCandidateResource;
use App\Http\Resources\Exams\InvitableExamResource;
use App\Http\Resources\Exams\SentInvitationResource;
use App\Models\Exam;
use App\Services\Exams\CollaboratorInvitationService;
use App\Support\ApiResponse;
use App\Support\CurrentUser;
use Illuminate\Http\JsonResponse;

/**
 * Invitar docentes colaboradores a un examen propio (HU-026).
 */
class CollaboratorInvitationController extends Controller
{
    private CollaboratorInvitationService $service;

    private CurrentUser $currentUser;

    public function __construct(CollaboratorInvitationService $service, CurrentUser $currentUser)
    {
        $this->service = $service;
        $this->currentUser = $currentUser;
    }

    /** GET /api/docente/invitaciones/examenes */
    public function exams(ListCollaboratorInvitationsRequest $request): JsonResponse
    {
        return ApiResponse::success(
            InvitableExamResource::collection($this->service->listInvitableExams($this->currentUser->teacherId()))
        );
    }

    /** GET /api/docente/invitaciones/examenes/{exam}/docentes?criterio= */
    public function candidates(SearchCollaboratorsRequest $request, Exam $exam): JsonResponse
    {
        $candidates = $this->service->searchCandidates(
            $exam,
            $this->currentUser->teacherId(),
            $request->validated()['criterio']
        );

        return ApiResponse::success(CollaboratorCandidateResource::collection($candidates));
    }

    /** POST /api/docente/invitaciones/examenes/{exam} */
    public function store(SendCollaboratorInvitationRequest $request, Exam $exam): JsonResponse
    {
        $data = $request->validated();

        $invitation = $this->service->send(
            $exam,
            $this->currentUser->teacherId(),
            $data['id_docente'],
            (bool) ($data['permite_agregar_grupos'] ?? true)
        );

        return ApiResponse::created(new SentInvitationResource($invitation), 'Invitación enviada correctamente.');
    }

    /** GET /api/docente/invitaciones */
    public function index(ListCollaboratorInvitationsRequest $request): JsonResponse
    {
        return ApiResponse::success(
            SentInvitationResource::collection($this->service->listSent($this->currentUser->teacherId()))
        );
    }
}
