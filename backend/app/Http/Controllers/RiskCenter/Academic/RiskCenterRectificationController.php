<?php

namespace App\Http\Controllers\RiskCenter\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\RiskCenter\Academic\RectifyRiskRecordRequest;
use App\Http\Requests\RiskCenter\Academic\SearchRectifiableStudentsRequest;
use App\Http\Requests\RiskCenter\Academic\ShowRectifiableRecordRequest;
use App\Http\Resources\RiskCenter\Academic\RectifiableRecordDetailResource;
use App\Http\Resources\RiskCenter\Academic\RectifiableStudentRecordsResource;
use App\Http\Resources\RiskCenter\Academic\RectifiableStudentResource;
use App\Http\Resources\RiskCenter\Academic\RectificationFaultTypeResource;
use App\Models\RiskCenter;
use App\Services\RiskCenter\Academic\RiskCenterRectificationQueryService;
use App\Services\RiskCenter\Academic\RiskCenterRectificationService;
use App\Support\CurrentUser;
use App\Support\Storage\EvidenceStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Rectificación de registros confirmados de la Central de Riesgo de estudiantes (HU-062). */
class RiskCenterRectificationController extends Controller
{
    private RiskCenterRectificationQueryService $queries;

    private RiskCenterRectificationService $rectifications;

    private EvidenceStorage $evidence;

    public function __construct(
        RiskCenterRectificationQueryService $queries,
        RiskCenterRectificationService $rectifications,
        EvidenceStorage $evidence
    ) {
        $this->queries = $queries;
        $this->rectifications = $rectifications;
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

        return new RectifiableStudentRecordsResource($result['estudiante'], $result['registros']);
    }

    /** GET /api/rectificacion-riesgo/registros/{id} */
    public function show(ShowRectifiableRecordRequest $request, int $id): RectifiableRecordDetailResource
    {
        return $this->detail($this->queries->findRecord($id));
    }

    /** POST /api/rectificacion-riesgo/registros/{id}/rectificar */
    public function rectify(RectifyRiskRecordRequest $request, CurrentUser $currentUser, int $id): JsonResponse
    {
        $this->rectifications->rectify(
            $id,
            $currentUser->id(),
            $request->faultTypeId(),
            $request->description(),
            $request->reason(),
            $request->baseVersion()
        );

        return $this->detail($this->queries->findRecord($id))
            ->response()
            ->setStatusCode(201);
    }

    /** GET /api/rectificacion-riesgo/tipos-falta */
    public function faultTypes(ShowRectifiableRecordRequest $request): AnonymousResourceCollection
    {
        return RectificationFaultTypeResource::collection($this->queries->faultTypes());
    }

    private function detail(RiskCenter $record): RectifiableRecordDetailResource
    {
        return new RectifiableRecordDetailResource(
            $record,
            $this->evidence->describe($record->studentReport->evidencia_public_id)
        );
    }
}
