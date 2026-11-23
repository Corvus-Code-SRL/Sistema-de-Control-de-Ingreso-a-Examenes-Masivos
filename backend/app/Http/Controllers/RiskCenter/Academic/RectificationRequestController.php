<?php

namespace App\Http\Controllers\RiskCenter\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\RiskCenter\Academic\AcceptRectificationRequestRequest;
use App\Http\Requests\RiskCenter\Academic\ListRectificationRequestsRequest;
use App\Http\Requests\RiskCenter\Academic\RejectRectificationRequestRequest;
use App\Http\Requests\RiskCenter\Academic\ShowRectificationEvidenceRequest;
use App\Http\Resources\RiskCenter\Academic\RectificationRequestResource;
use App\Services\RiskCenter\Academic\RectificationEvidenceService;
use App\Services\RiskCenter\Academic\RectificationRequestQueryService;
use App\Services\RiskCenter\Academic\RectificationRequestService;
use App\Support\CurrentUser;
use App\Support\Storage\EvidenceStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Solicitudes de rectificación de la Central de Riesgo (HU-062), lado del docente que aprobó el reporte. */
class RectificationRequestController extends Controller
{
    private RectificationRequestQueryService $queries;

    private RectificationRequestService $requests;

    private RectificationEvidenceService $requestEvidence;

    private EvidenceStorage $evidence;

    public function __construct(
        RectificationRequestQueryService $queries,
        RectificationRequestService $requests,
        RectificationEvidenceService $requestEvidence,
        EvidenceStorage $evidence
    ) {
        $this->queries = $queries;
        $this->requests = $requests;
        $this->requestEvidence = $requestEvidence;
        $this->evidence = $evidence;
    }

    /** GET /api/solicitudes-rectificacion */
    public function index(
        ListRectificationRequestsRequest $request,
        CurrentUser $currentUser
    ): AnonymousResourceCollection {
        $inbox = $this->queries->inbox($currentUser->id());

        return RectificationRequestResource::collection($inbox['solicitudes'])->additional([
            'meta' => ['total_pendientes' => $inbox['total_pendientes']],
        ]);
    }

    /** GET /api/solicitudes-rectificacion/conteo */
    public function count(ListRectificationRequestsRequest $request, CurrentUser $currentUser): JsonResponse
    {
        return response()->json([
            'data' => ['total_pendientes' => $this->queries->countPending($currentUser->id())],
        ]);
    }

    /** POST /api/solicitudes-rectificacion/{id}/aceptar */
    public function accept(
        AcceptRectificationRequestRequest $request,
        CurrentUser $currentUser,
        int $id
    ): RectificationRequestResource {
        $this->requests->accept($id, $currentUser->id());

        return new RectificationRequestResource($this->queries->findForTeacher($id, $currentUser->id()));
    }

    /** POST /api/solicitudes-rectificacion/{id}/rechazar */
    public function reject(
        RejectRectificationRequestRequest $request,
        CurrentUser $currentUser,
        int $id
    ): RectificationRequestResource {
        $this->requests->reject($id, $currentUser->id(), $request->reason(), $request->image());

        return new RectificationRequestResource($this->queries->findForTeacher($id, $currentUser->id()));
    }

    /** GET /api/solicitudes-rectificacion/{id}/evidencia-reporte */
    public function reportEvidence(
        ShowRectificationEvidenceRequest $request,
        CurrentUser $currentUser,
        int $id
    ): StreamedResponse {
        return $this->evidence->response($this->requestEvidence->locateReportEvidence($id, $currentUser->id()));
    }

    /** GET /api/solicitudes-rectificacion/{id}/evidencia-rechazo */
    public function rejectionEvidence(
        ShowRectificationEvidenceRequest $request,
        CurrentUser $currentUser,
        int $id
    ): StreamedResponse {
        return $this->evidence->response($this->requestEvidence->locateRejectionEvidence($id, $currentUser->id()));
    }
}
