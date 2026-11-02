<?php

namespace App\Http\Controllers\Incidents;

use App\Http\Controllers\Controller;
use App\Http\Requests\Incidents\StoreInProgressIncidentRequest;
use App\Http\Resources\Incidents\IncidentReportResource;
use App\Services\Incidents\InProgressIncidentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class InProgressIncidentController extends Controller
{
    public function store(StoreInProgressIncidentRequest $request, InProgressIncidentService $service): JsonResponse
    {
        $input = $request->validated();
        $report = $service->report(
            (int) $input['examen'],
            (int) $input['estudiante'],
            $input,
            $request->file('evidencia'),
            $request->user()
        );

        return ApiResponse::created(new IncidentReportResource($report), 'Enviado a revisión del docente');
    }
}
