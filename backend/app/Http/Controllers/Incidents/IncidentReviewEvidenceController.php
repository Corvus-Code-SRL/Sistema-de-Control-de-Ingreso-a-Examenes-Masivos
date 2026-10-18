<?php

namespace App\Http\Controllers\Incidents;

use App\Http\Controllers\Controller;
use App\Http\Requests\Incidents\ShowIncidentReportRequest;
use App\Services\Incidents\IncidentReviewService;
use App\Support\CurrentUser;
use App\Support\Storage\EvidenceStorage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Entrega la evidencia de una incidencia solo al docente dueño del examen. */
class IncidentReviewEvidenceController extends Controller
{
    private IncidentReviewService $incidents;

    private EvidenceStorage $storage;

    public function __construct(IncidentReviewService $incidents, EvidenceStorage $storage)
    {
        $this->incidents = $incidents;
        $this->storage   = $storage;
    }

    /** GET /api/docente/incidencias/{reporte}/evidencia */
    public function show(ShowIncidentReportRequest $request, CurrentUser $currentUser, string $reporte): StreamedResponse
    {
        $publicId = $this->incidents->findEvidencePublicId($currentUser->id(), $reporte);

        $location = $this->storage->locate($publicId);

        if ($location === null) {
            abort(404, 'La incidencia no tiene evidencia disponible.');
        }

        return $this->storage->response($location);
    }
}