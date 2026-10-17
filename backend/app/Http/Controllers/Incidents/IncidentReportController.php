<?php

namespace App\Http\Controllers\Incidents;

use App\Http\Controllers\Controller;
use App\Http\Requests\Incidents\ApproveIncidentReportRequest;
use App\Http\Requests\Incidents\ListIncidentReportsRequest;
use App\Http\Requests\Incidents\RejectIncidentReportRequest;
use App\Http\Requests\Incidents\ShowIncidentReportRequest;
use App\Http\Resources\Incidents\IncidentReportDetailResource;
use App\Http\Resources\Incidents\IncidentReportResource;
use App\Services\Incidents\IncidentReportService;
use App\Support\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class IncidentReportController extends Controller
{
    private IncidentReportService $incidents;

    public function __construct(IncidentReportService $incidents)
    {
        $this->incidents = $incidents;
    }

    public function index(ListIncidentReportsRequest $request, CurrentUser $currentUser): AnonymousResourceCollection
    {
        $teacherId = $currentUser->id();
        $estado    = $request->input('estado', 'pendiente');
        $examId    = $request->input('examen');

        $result = $this->incidents->list($teacherId, $estado, $examId !== null ? (int) $examId : null);

        return IncidentReportResource::collection($result['data'])
            ->additional(['meta' => $result['meta']]);
    }

    public function show(ShowIncidentReportRequest $request, CurrentUser $currentUser, string $reporte): IncidentReportDetailResource
    {
        return new IncidentReportDetailResource(
            $this->incidents->find($currentUser->id(), $reporte)
        );
    }

    public function approve(ApproveIncidentReportRequest $request, CurrentUser $currentUser, string $reporte): IncidentReportDetailResource
    {
        return new IncidentReportDetailResource(
            $this->incidents->approve($currentUser->id(), $reporte, $request->input('observacion'))
        );
    }

    public function reject(RejectIncidentReportRequest $request, CurrentUser $currentUser, string $reporte): IncidentReportDetailResource
    {
        return new IncidentReportDetailResource(
            $this->incidents->reject($currentUser->id(), $reporte, (string) $request->input('observacion'))
        );
    }
}