<?php

namespace App\Http\Controllers\Exams;

use App\Http\Controllers\Controller;
use App\Http\Requests\Exams\AcceptInvitationRequest;
use App\Http\Requests\Exams\ListReceivedInvitationsRequest;
use App\Http\Requests\Exams\ShowReceivedInvitationRequest;
use App\Http\Resources\Exams\ReceivedInvitationResource;
use App\Services\Exams\ExamInvitationResponseService;
use App\Support\ApiResponse;
use App\Support\CurrentUser;
use Illuminate\Http\JsonResponse;

/**
 * Bandeja de invitaciones recibidas y su respuesta (HU-027).
 */
class ReceivedInvitationController extends Controller
{
    private ExamInvitationResponseService $service;

    private CurrentUser $currentUser;

    public function __construct(ExamInvitationResponseService $service, CurrentUser $currentUser)
    {
        $this->service = $service;
        $this->currentUser = $currentUser;
    }

    /** GET /api/invitaciones?estado=pendientes|aceptadas|cerradas */
    public function index(ListReceivedInvitationsRequest $request): JsonResponse
    {
        $result = $this->service->listReceived($this->currentUser->teacherId(), $request->tab());

        return response()->json([
            'data' => ReceivedInvitationResource::collection($result['invitaciones'])->resolve($request),
            'meta' => [
                'total_pendientes' => $result['totales']['pendientes'],
                'total_aceptadas' => $result['totales']['aceptadas'],
                'total_cerradas' => $result['totales']['cerradas'],
            ],
        ]);
    }

    /** GET /api/invitaciones/{invitation} */
    public function show(ShowReceivedInvitationRequest $request, int $invitation): JsonResponse
    {
        return ApiResponse::success(
            new ReceivedInvitationResource($this->service->show($invitation, $this->currentUser->teacherId()))
        );
    }

    /** POST /api/invitaciones/{invitation}/aceptar */
    public function accept(AcceptInvitationRequest $request, int $invitation): JsonResponse
    {
        $accepted = $this->service->accept($invitation, $this->currentUser->teacherId(), $request->groupIds());

        return ApiResponse::success(new ReceivedInvitationResource($accepted), 'Invitación aceptada.');
    }

    /** POST /api/invitaciones/{invitation}/rechazar */
    public function reject(ShowReceivedInvitationRequest $request, int $invitation): JsonResponse
    {
        $rejected = $this->service->reject($invitation, $this->currentUser->teacherId());

        return ApiResponse::success(new ReceivedInvitationResource($rejected), 'Invitación rechazada.');
    }
}
