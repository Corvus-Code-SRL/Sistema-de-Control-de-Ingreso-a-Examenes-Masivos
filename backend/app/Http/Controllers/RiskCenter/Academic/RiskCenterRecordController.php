<?php

namespace App\Http\Controllers\RiskCenter\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\RiskCenter\Academic\IndexRiskCenterRecordRequest;
use App\Http\Requests\RiskCenter\Academic\ShowRiskCenterRecordRequest;
use App\Http\Resources\RiskCenter\Academic\RiskCenterRecordDetailResource;
use App\Http\Resources\RiskCenter\Academic\RiskCenterRecordResource;
use App\Services\RiskCenter\Academic\RiskCenterQueryService;
use App\Support\Storage\EvidenceStorage;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Consulta de solo lectura de la Central de Riesgo de estudiantes. */
class RiskCenterRecordController extends Controller
{
    private RiskCenterQueryService $records;

    private EvidenceStorage $evidence;

    public function __construct(RiskCenterQueryService $records, EvidenceStorage $evidence)
    {
        $this->records = $records;
        $this->evidence = $evidence;
    }

    /** GET /api/central-riesgo */
    public function index(IndexRiskCenterRecordRequest $request): AnonymousResourceCollection
    {
        return RiskCenterRecordResource::collection($this->records->paginate(
            $request->search(),
            $request->faultTypeId(),
            $request->page(),
            $request->perPage()
        ));
    }

    /** GET /api/central-riesgo/{id} */
    public function show(ShowRiskCenterRecordRequest $request, int $id): RiskCenterRecordDetailResource
    {
        $record = $this->records->find($id);

        return new RiskCenterRecordDetailResource(
            $record,
            $this->evidence->describe($record->studentReport->evidencia_public_id)
        );
    }
}
