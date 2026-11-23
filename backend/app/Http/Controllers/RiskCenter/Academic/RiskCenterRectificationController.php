<?php

namespace App\Http\Controllers\RiskCenter\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\RiskCenter\Academic\RectifyRiskRecordRequest;
use App\Http\Requests\RiskCenter\Academic\SearchRectifiableStudentsRequest;
use App\Http\Requests\RiskCenter\Academic\ShowRectifiableRecordRequest;
use App\Http\Requests\RiskCenter\Academic\ShowRectificationRequestEvidenceRequest;
use App\Http\Requests\RiskCenter\Academic\StoreRectificationRequestRequest;
use App\Http\Resources\RiskCenter\Academic\RectifiableRecordDetailResource;
use App\Http\Resources\RiskCenter\Academic\RectifiableStudentRecordsResource;
use App\Http\Resources\RiskCenter\Academic\RectifiableStudentResource;
use App\Http\Resources\RiskCenter\Academic\RectificationRequestSentResource;
use App\Http\Resources\RiskCenter\Academic\RectifiedRecordResource;
use App\Services\RiskCenter\Academic\RectificationEvidenceService;
use App\Services\RiskCenter\Academic\RectificationRequestService;
use App\Services\RiskCenter\Academic\RiskCenterRectificationQueryService;
use App\Services\RiskCenter\Academic\RiskCenterRectificationService;
use App\Support\CurrentUser;
use App\Support\Storage\EvidenceStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Rectificación de registros de la Central de Riesgo de estudiantes (HU-062), lado del Administrador: consulta los
 * registros, envía la solicitud al docente que aprobó el reporte y, si ese docente ya no está activo, retira el
 * registro sin su confirmación.
 */
class RiskCenterRectificationController extends Controller
{
    private RiskCenterRectificationQueryService $queries;

    private RiskCenterRectificationService $rectifications;

    private RectificationRequestService $requests;

    private RectificationEvidenceService $requestEvidence;

    private EvidenceStorage $evidence;

    public function __construct(
        RiskCenterRectificationQueryService $queries,
        RiskCenterRectificationService $rectifications,
        RectificationRequestService $requests,
        RectificationEvidenceService $requestEvidence,
        EvidenceStorage $evidence
    ) {
        $this->queries = $queries;
        $this->rectifications = $rectifications;
        $this->requests = $requests;
        $this->requestEvidence = $requestEvidence;
        $this->evidence = $evidence;
    }

    /** GET /api/rectificacion-riesgo/estudiantes?q= */
    public function students(SearchRectifiableStudentsRequest $request): AnonymousResourceCollection
    {
        $result = $this->queries->searchStudents($request->term());

        return RectifiableStudentResource::collection($result['estudiantes'])->additional([
            'meta' => [
                'limite' => $this->queries::STUDENT_LIMIT,
                'hay_mas' => $result['hay_mas'],
            ],
        ]);
    }

    /** GET /api/rectificacion-riesgo/estudiantes/{id}/registros */
    public function studentRecords(ShowRectifiableRecordRequest $request, int $id): RectifiableStudentRecordsResource
    {
        $result = $this->queries->recordsOfStudent($id);

        return new RectifiableStudentRecordsResource(
            $result['estudiante'],
            $result['registros'],
            $result['rectificados']
        );
    }

    /** GET /api/rectificacion-riesgo/registros/{id} */
    public function show(ShowRectifiableRecordRequest $request, int $id): RectifiableRecordDetailResource
    {
        $record = $this->queries->findRecord($id);

        return new RectifiableRecordDetailResource(
            $record,
            $this->evidence->describe($record->studentReport->evidencia_public_id)
        );
    }

    /** POST /api/rectificacion-riesgo/registros/{id}/solicitudes */
    public function requestRectification(
        StoreRectificationRequestRequest $request,
        CurrentUser $currentUser,
        int $id
    ): JsonResponse {
        $sent = $this->requests->send($id, $currentUser->id(), $request->reason());

        return (new RectificationRequestSentResource($sent->load('teacher')))
            ->response()
            ->setStatusCode(201);
    }

    /** POST /api/rectificacion-riesgo/registros/{id}/rectificar-sin-confirmacion */
    public function rectifyWithoutConfirmation(
        RectifyRiskRecordRequest $request,
        CurrentUser $currentUser,
        int $id
    ): RectifiedRecordResource {
        $this->rectifications->rectifyWithoutConfirmation($id, $currentUser->id(), $request->reason());

        return new RectifiedRecordResource($this->queries->findRectifiedRecord($id));
    }

    /** GET /api/rectificacion-riesgo/solicitudes/{id}/evidencia */
    public function rejectionEvidence(ShowRectificationRequestEvidenceRequest $request, int $id): StreamedResponse
    {
        return $this->evidence->response($this->requestEvidence->locateRejectionEvidence($id));
    }
}
