<?php

namespace App\Http\Controllers\RiskCenter\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\RiskCenter\Academic\ShowRiskCenterEvidenceRequest;
use App\Services\RiskCenter\Academic\RiskCenterEvidenceService;
use App\Support\Storage\EvidenceStorage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Entrega la evidencia de un registro solo a quien puede consultar la Central de Riesgo. */
class RiskCenterEvidenceController extends Controller
{
    private RiskCenterEvidenceService $evidenceFiles;

    private EvidenceStorage $storage;

    public function __construct(RiskCenterEvidenceService $evidenceFiles, EvidenceStorage $storage)
    {
        $this->evidenceFiles = $evidenceFiles;
        $this->storage = $storage;
    }

    /** GET /api/central-riesgo/{id}/evidencia */
    public function show(ShowRiskCenterEvidenceRequest $request, int $id): StreamedResponse
    {
        return $this->storage->response($this->evidenceFiles->locate($id));
    }
}
